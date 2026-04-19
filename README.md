# Event Loader

Core of a distributed event-loading system. Collects immutable events from
multiple remote sources into a centralized storage. Designed so that any
number of loader instances (on any number of servers) can run in parallel
without transporting the same event twice.

## Requirements

- PHP **8.1+**
- Composer
- `make` (optional, there is also a plain `composer` workflow)

## Installation

```bash
make install
# equivalent to:
composer install
```

## Running the tests

```bash
make check             # CS dry-run + PHPStan + tests (CI-style, non-modifying)
make all               # CS auto-fix + PHPStan + tests (local shortcut)
```

All tests should pass.

## Architecture

The code follows **Ports & Adapters** (aka Hexagonal): the core loader
orchestrates collaborators through narrow interfaces (ports). No concrete
backend – HTTP, Redis, Postgres, Kafka, … – is referenced from the domain.

```
┌──────────────────────────────────────────────────────────────┐
│                  RoundRobinEventLoader                       │
│                  (the one implementation)                    │
└─────┬─────────────┬──────────────┬──────────────┬────────────┘
      │             │              │              │
┌─────▼───────┐ ┌───▼──────────┐ ┌─▼─────────┐ ┌──▼─────────────┐
│EventFetcher │ │EventStore    │ │LockFactory│ │SourceGate      │
│(port)       │ │(port)        │ │(port)     │ │(port)          │
└─────────────┘ └──────────────┘ └───────────┘ └────────────────┘
  ↑ adapter:     ↑ adapter:       ↑ adapter:    ↑ adapter:
  HttpFetcher    DoctrineStore    RedisLock     RedisGate
  ...            PostgresStore    ...           DbGate
                 ...                            ...
```

### Module layout

```
src/
├── Event.php                      # immutable value object {id, payload}
├── Source/
│   └── AbstractSource.php         # abstract identity (name); subtypes add
│                                  # transport-specific config (URL, topic…)
├── EventFetcher/
│   ├── EventFetcherInterface.php  # fetch(source, afterId, limit): list<Event>
│   └── EventFetchException.php
├── EventStore/
│   └── EventStoreInterface.php    # lastKnownId() + atomic append()
├── SourceGate/
│   └── SourceGateInterface.php    # 200ms global rate limit per source
├── Lock/
│   ├── LockInterface.php          # tryAcquire() / release()
│   └── LockFactoryInterface.php
└── Loader/
    ├── EventLoaderInterface.php
    └── RoundRobinEventLoader.php  # coordinator (the one thing implemented)
```

## How the coordinator works

Per round over all configured sources:

1. **Grab a distributed per-source lock** (non-blocking). If another loader
   instance already owns it, skip this source this round.
2. **Try to reserve a gate slot** (also non-blocking). The gate enforces the
   global ≥200ms gap between two requests to the same source. Denied → skip.
3. **Read the cursor** (`lastKnownId`) from the store.
4. **Fetch up to 1000 events** with `id > cursor`.
5. **Atomically append** the batch together with the advanced cursor.
6. `finally` → **release the lock**.

Network / server errors raised by the fetcher are caught, logged at
`WARNING`, and the source is skipped. The main loop never crashes on
per-source failures.

When an entire round produced no work, the loader sleeps briefly (default
100ms) to avoid busy-waiting.

## Key design decisions

### No-duplicate-transport guarantee

Two layers of defence, neither of them sufficient alone:

- **Per-source distributed lock** – at most one instance fetches from a
  given source at any time, so two instances cannot both ask for the same
  `id > cursor` range.
- **Atomic `EventStore::append()`** – persists events **and** advances the
  cursor in one transaction. A crash between "fetch succeeded" and "store
  committed" would otherwise lead to re-fetching the same events next time,
  i.e. the same event transported twice. The task explicitly calls that a
  conflict.

### Rate limit is separate from the lock

The lock answers *"who may fetch?"*; the gate answers *"when may they?"*.
Keeping them separate makes the design orthogonal and lets any backend
(Redis `SET NX PX 200`, DB row with `updated_at`, …) implement each as it
sees fit.

Both checks are non-blocking: when denied, the round-robin simply moves on
to the next source.

### Protocol- and format-agnostic

The task explicitly requires:

> Interfaces should be designed to be independent of the protocol or message
> format used for network communication.

Therefore:

- `Event::$payload` is a plain associative array, not a JSON string or a
  framework DTO.
- `AbstractSource` is deliberately abstract and only carries a `$name`.
  Concrete transports (`HttpSource`, `KafkaSource`, `FileSource`, …) extend
  it and add their own fields.
- Because PHP forbids narrowing parameter types, a concrete
  `EventFetcherInterface` implementation resolves its preferred subtype
  either with an `instanceof` guard or with PHPStan generics
  (`@implements EventFetcherInterface<HttpSource>`). This is documented on
  `AbstractSource` itself.

### `runOnce()` for testability

`RoundRobinEventLoader::run()` is an infinite loop (as required). Tests
drive the loader via the public `runOnce()` method, which performs exactly
one round-robin pass and returns whether any source produced work. That
keeps tests deterministic and fast.

## Running multiple instances

Spin up N processes with the same configuration, each building its own
`RoundRobinEventLoader` wired to the **same** concrete `LockFactory`,
`SourceGate`, `EventStore` and `EventFetcher`. The store of locks and
gate reservations must be shared across instances (typically Redis) — that
is the reason those two are ports, not local in-memory implementations.

## What this repository does **not** implement

Per the task spec, the following are intentionally left as ports:

- A concrete `EventFetcher` (HTTP / gRPC / …).
- A concrete `EventStore` (Postgres / MySQL / …).
- A concrete `LockFactory` (Redis / Postgres advisory lock / Flock).
- A concrete `SourceGate` (Redis `SET NX PX 200` is the expected default).
- A CLI entry point wiring them together.

Suggested reference adapters, if you decide to plug them in:

| Port | Backend | Hint |
|---|---|---|
| `LockFactoryInterface` | Redis | `SET {key} {owner} NX PX {ttl_ms}` + Lua for release |
| `SourceGateInterface` | Redis | `SET gate:{source} 1 NX PX 200` — OK ⇒ reserved |
| `EventStoreInterface` | Postgres | single TX: `INSERT events ... ; UPDATE cursors ...` |
| `EventFetcherInterface` | HTTP | `symfony/http-client`; decode JSON to `Event` list |

## Development

```bash
make help
```

## License

Proprietary.

<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Event;
use App\EventStore\EventStoreInterface;
use App\Source\AbstractSource;

/**
 * Simple in-memory {@see EventStoreInterface} that keeps a per-source list
 * of events and records every {@see append()} call.
 */
final class InMemoryEventStore implements EventStoreInterface
{
    /** @var array<string, list<Event>> */
    public array $stored = [];

    /** @var list<array{source: string, events: list<Event>}> */
    public array $appendCalls = [];

    private ?\Closure $appendFailure = null;

    public function failAppendOnce(\Throwable $error): void
    {
        $this->appendFailure = static function () use ($error): void {
            throw $error;
        };
    }

    public function lastKnownId(AbstractSource $source): int
    {
        $events = $this->stored[$source->name] ?? [];
        if ([] === $events) {
            return 0;
        }

        $last = $events[array_key_last($events)];

        return $last->id;
    }

    public function append(AbstractSource $source, array $events): void
    {
        $this->appendCalls[] = ['source' => $source->name, 'events' => $events];

        if (null !== $this->appendFailure) {
            $failure = $this->appendFailure;
            $this->appendFailure = null;
            $failure();
        }

        foreach ($events as $event) {
            $this->stored[$source->name][] = $event;
        }
    }
}

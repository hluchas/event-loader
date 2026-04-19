<?php

declare(strict_types=1);

namespace App\Source;

/**
 * Base type for an event source identified by a unique name.
 *
 * This class is abstract on purpose: concrete transports carry their own
 * configuration (URL, topic, file path, credentials, …), so a realistic
 * deployment will always introduce a subtype such as `HttpSource`,
 * `KafkaSource`, `InMemorySource`, etc.
 *
 * The core loader never inspects those extras — it treats sources as mere
 * identities. A concrete {@see \App\EventFetcher\EventFetcherInterface} implementation
 * does use them; because PHP forbids narrowing parameter types, such an
 * implementation is expected to either:
 *
 *  - assert the subtype with `instanceof` at the top of `fetch()`, or
 *  - document it via PHPStan generics
 *    (`@implements EventFetcherInterface<HttpSource>`).
 *
 * Equality is defined by {@see $name} alone across the whole system.
 */
abstract class AbstractSource
{
    public function __construct(
        public readonly string $name,
    ) {
        if ('' === $name) {
            throw new \InvalidArgumentException('Source name must not be empty.');
        }
    }
}

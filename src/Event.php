<?php

declare(strict_types=1);

namespace App;

/**
 * Immutable event as delivered by a source.
 */
final class Event
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        /**
         * Unique within its source and strictly increasing with event occurrence time (later event => greater id).
         */
        public readonly int $id,

        /**
         * Plain associative array so the domain stays independent of the wire format (JSON/XML/…). Decoding from the
         * transport format is the concrete fetcher's responsibility.
         */
        public readonly array $payload,
    ) {
    }
}

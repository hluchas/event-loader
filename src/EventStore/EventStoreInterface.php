<?php

declare(strict_types=1);

namespace App\EventStore;

use App\Event;
use App\Source\AbstractSource;

/**
 * Persistent storage of events and per-source cursors.
 *
 * The implementation is assumed to be fault-tolerant: if a method returns
 * without throwing, the data is durably stored.
 */
interface EventStoreInterface
{
    /**
     * Returns the largest event id ever persisted for the source,
     * or 0 if none have been stored yet.
     *
     * Used by the coordinator as the cursor for the next fetch.
     */
    public function lastKnownId(AbstractSource $source): int;

    /**
     * Atomically persist a batch of events for the source and advance its
     * cursor to the id of the last event in the batch.
     *
     * Preconditions (caller's responsibility):
     *  - $events is non-empty,
     *  - events are sorted by id ascending,
     *  - every event id is strictly greater than {@see lastKnownId()} at the
     *    time of call.
     *
     * @param list<Event> $events
     */
    public function append(AbstractSource $source, array $events): void;
}

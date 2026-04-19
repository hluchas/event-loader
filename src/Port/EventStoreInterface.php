<?php

declare(strict_types=1);

namespace App\Port;

use App\Domain\AbstractSource;
use App\Domain\Event;

/**
 * Persistent storage of events and per-source cursors.
 *
 * The implementation is assumed to be fault-tolerant: if a method returns
 * without throwing, the data is durably stored.
 *
 * Atomicity requirement: {@see append()} MUST persist events and advance the
 * cursor in a single transaction. Anything less would allow the same event
 * to be re-fetched (and thus transported over the network more than once)
 * after a crash, which the task forbids.
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

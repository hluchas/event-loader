<?php

declare(strict_types=1);

namespace App\Port;

use App\Domain\AbstractSource;
use App\Domain\Event;
use App\Exception\EventFetchException;

/**
 * Retrieves new events from a remote source.
 *
 * Implementations are protocol- and format-agnostic from the caller's
 * perspective: the caller only provides the source and the last known
 * event id and receives a list of new {@see Event} objects.
 */
interface EventFetcherInterface
{
    /**
     * Fetch up to $limit events from the source with id strictly greater
     * than $afterId, ordered by id ascending.
     *
     * An empty array means "no new events right now".
     *
     * @param int $afterId last known event id; pass 0 to fetch from the beginning
     * @param int $limit   maximum number of events to return (source cap is 1000)
     *
     * @return list<Event> ordered by id ascending, length in range [0, $limit]
     *
     * @throws EventFetchException on network/server errors or malformed response
     */
    public function fetch(AbstractSource $source, int $afterId, int $limit): array;
}

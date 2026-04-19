<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown by an {@see \App\Port\EventFetcherInterface} when it cannot obtain events
 * from the remote source (network failure, timeout, 5xx response, malformed
 * payload, …).
 *
 * The coordinator catches this exception, logs it and moves on to the next
 * source.
 */
class EventFetchException extends \RuntimeException
{
}

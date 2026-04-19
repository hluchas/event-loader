<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Event;
use App\EventFetcher\EventFetcherInterface;
use App\EventFetcher\EventFetchException;
use App\Source\AbstractSource;

/**
 * Test fetcher driven by a user-supplied closure. Records every call so
 * tests can assert the coordinator passed the right arguments.
 */
final class CallableEventFetcher implements EventFetcherInterface
{
    /** @var list<array{source: AbstractSource, afterId: int, limit: int}> */
    public array $calls = [];

    /** @var \Closure(AbstractSource, int, int): list<Event> */
    private \Closure $handler;

    /**
     * @param callable(AbstractSource, int, int): list<Event> $handler
     */
    public function __construct(callable $handler)
    {
        $this->handler = \Closure::fromCallable($handler);
    }

    /**
     * @param list<Event> $events
     */
    public static function alwaysReturns(array $events): self
    {
        return new self(static fn (): array => $events);
    }

    public static function alwaysEmpty(): self
    {
        return new self(static fn (): array => []);
    }

    public static function alwaysThrows(string $message = 'boom'): self
    {
        return new self(static function () use ($message): array {
            throw new EventFetchException($message);
        });
    }

    /**
     * @return list<Event>
     */
    public function fetch(AbstractSource $source, int $afterId, int $limit): array
    {
        $this->calls[] = ['source' => $source, 'afterId' => $afterId, 'limit' => $limit];

        return ($this->handler)($source, $afterId, $limit);
    }
}

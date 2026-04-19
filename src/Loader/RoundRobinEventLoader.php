<?php

declare(strict_types=1);

namespace App\Loader;

use App\Domain\AbstractSource;
use App\Exception\EventFetchException;
use App\Port\EventFetcherInterface;
use App\Port\EventStoreInterface;
use App\Port\LockFactoryInterface;
use App\Port\SourceGateInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Reference implementation of {@see EventLoaderInterface}.
 *
 * Iterates configured sources in a round-robin fashion. For each source:
 *
 *   1. Tries to grab a distributed per-source lock (non-blocking). If another
 *      instance already owns it, the source is skipped this round. This is
 *      the primary mechanism that prevents two instances from fetching the
 *      same id range (and therefore transporting the same event twice).
 *
 *   2. Tries to reserve a request slot via {@see SourceGateInterface}, which
 *      enforces the global >=200ms gap between consecutive requests to the
 *      same source. If the slot is denied, the source is skipped.
 *
 *   3. Reads the cursor from {@see EventStoreInterface::lastKnownId()},
 *      fetches a batch (up to 1000 events) and atomically appends them
 *      together with an advanced cursor via
 *      {@see EventStoreInterface::append()}.
 *
 *   4. Network/server errors raised by the fetcher are logged at WARNING
 *      and the source is simply skipped, as required by the task.
 *
 * When a full round produces no work, the loop sleeps briefly to avoid
 * busy-waiting.
 */
final class RoundRobinEventLoader implements EventLoaderInterface
{
    public const DEFAULT_BATCH_SIZE = 1000;
    public const DEFAULT_IDLE_SLEEP_MS = 100;
    public const DEFAULT_LOCK_TTL_SECONDS = 30.0;

    /** @var list<AbstractSource> */
    private readonly array $sources;

    private readonly LoggerInterface $logger;

    /**
     * @param iterable<AbstractSource> $sources
     */
    public function __construct(
        iterable $sources,
        private readonly EventFetcherInterface $fetcher,
        private readonly EventStoreInterface $store,
        private readonly LockFactoryInterface $lockFactory,
        private readonly SourceGateInterface $gate,
        ?LoggerInterface $logger = null,
        private readonly int $batchSize = self::DEFAULT_BATCH_SIZE,
        private readonly int $idleSleepMs = self::DEFAULT_IDLE_SLEEP_MS,
        private readonly float $lockTtlSeconds = self::DEFAULT_LOCK_TTL_SECONDS,
    ) {
        $materialized = [];
        foreach ($sources as $source) {
            $materialized[] = $source;
        }

        if ([] === $materialized) {
            throw new \InvalidArgumentException('At least one source is required.');
        }

        if ($this->batchSize < 1 || $this->batchSize > 1000) {
            throw new \InvalidArgumentException('Batch size must be between 1 and 1000.');
        }

        $this->sources = $materialized;
        $this->logger = $logger ?? new NullLogger();
    }

    public function run(): void
    {
        /* Make sure we are not tripped by a non-default `max_execution_time`.
        set_time_limit(0);

        /* The loop exits on:
         *   - an external signal (SIGTERM/SIGINT) — expected shutdown path,
         *   - an uncaught Throwable — e.g. a storage-level failure; fail fast.
         */
        // @phpstan-ignore while.alwaysTrue
        while (true) {
            if (!$this->runOnce()) {
                usleep($this->idleSleepMs * 1000);
            }
        }
    }

    /**
     * Execute one full round over all sources. Exposed for testing so a test
     * can drive the loader without blocking on an infinite loop.
     *
     * @return bool true if at least one source produced events this round
     */
    public function runOnce(): bool
    {
        $workDone = false;
        foreach ($this->sources as $source) {
            if ($this->processSource($source)) {
                $workDone = true;
            }
        }

        return $workDone;
    }

    private function processSource(AbstractSource $source): bool
    {
        $lock = $this->lockFactory->createLock(
            'event-loader:source:'.$source->name,
            $this->lockTtlSeconds,
        );

        if (!$lock->tryAcquire()) {
            return false;
        }

        try {
            if (!$this->gate->reserve($source)) {
                return false;
            }

            $cursor = $this->store->lastKnownId($source);

            try {
                $events = $this->fetcher->fetch($source, $cursor, $this->batchSize);
            } catch (EventFetchException $e) {
                $this->logger->warning('Event source unavailable, skipping.', [
                    'source' => $source->name,
                    'cursor' => $cursor,
                    'exception' => $e,
                ]);

                return false;
            }

            if ([] === $events) {
                return false;
            }

            $this->store->append($source, $events);

            return true;
        } finally {
            $lock->release();
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Loader;

use App\Event;
use App\EventFetcher\EventFetchException;
use App\Loader\RoundRobinEventLoader;
use App\Source\AbstractSource;
use App\Tests\Support\ArrayLogger;
use App\Tests\Support\CallableEventFetcher;
use App\Tests\Support\InMemoryEventStore;
use App\Tests\Support\RecordingLockFactory;
use App\Tests\Support\TestSource;
use App\Tests\Support\ToggleSourceGate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class RoundRobinEventLoaderTest extends TestCase
{
    #[Test]
    public function itRefusesAnEmptySourceList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->buildLoader(sources: []);
    }

    #[Test]
    public function itRefusesAnOutOfRangeBatchSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->buildLoader(batchSize: 1001);
    }

    #[Test]
    public function itFetchesAndAppendsEventsOnHappyPath(): void
    {
        $source = new TestSource('orders');
        $events = [new Event(1, ['x' => 1]), new Event(2, ['x' => 2])];

        $ctx = new LoaderContext(sources: [$source], fetcher: CallableEventFetcher::alwaysReturns($events));

        self::assertTrue($ctx->loader->runOnce());
        self::assertSame(
            [['source' => 'orders', 'events' => $events]],
            $ctx->store->appendCalls,
            'happy path must persist the exact batch returned by the fetcher',
        );
        self::assertSame(['event-loader:source:orders'], $ctx->lockFactory->released);
    }

    #[Test]
    public function itDoesNotAppendWhenTheFetcherReturnsNoEvents(): void
    {
        $ctx = new LoaderContext(fetcher: CallableEventFetcher::alwaysEmpty());

        self::assertFalse($ctx->loader->runOnce(), 'an empty round must report no work done');
        self::assertSame([], $ctx->store->appendCalls);
        self::assertSame(['event-loader:source:orders'], $ctx->lockFactory->released);
    }

    #[Test]
    public function itSkipsTheSourceWhenTheLockCannotBeAcquired(): void
    {
        $ctx = new LoaderContext();
        $ctx->lockFactory->deny('event-loader:source:orders');

        self::assertFalse($ctx->loader->runOnce());
        self::assertSame([], $ctx->gate->calls, 'gate must not be consulted when lock is denied');
        self::assertSame([], $ctx->fetcher->calls, 'fetcher must not be called when lock is denied');
        self::assertSame([], $ctx->store->appendCalls);
        self::assertSame([], $ctx->lockFactory->released, 'release is only emitted for locks we actually held');
    }

    #[Test]
    public function itSkipsFetchingWhenTheGateDeniesTheSlotButStillReleasesTheLock(): void
    {
        $ctx = new LoaderContext();
        $ctx->gate->deny('orders');

        self::assertFalse($ctx->loader->runOnce());
        self::assertSame([], $ctx->fetcher->calls, 'fetcher must not be called when gate denies');
        self::assertSame([], $ctx->store->appendCalls);
        self::assertSame(['event-loader:source:orders'], $ctx->lockFactory->released);
    }

    #[Test]
    public function itLogsAWarningAndSkipsWhenTheFetcherFails(): void
    {
        $ctx = new LoaderContext(fetcher: CallableEventFetcher::alwaysThrows('timeout'));

        self::assertFalse($ctx->loader->runOnce(), 'a failed fetch must not count as work');
        self::assertSame([], $ctx->store->appendCalls, 'nothing must be stored when fetch failed');
        self::assertSame(
            ['event-loader:source:orders'],
            $ctx->lockFactory->released,
            'lock must be released even when the fetcher throws',
        );

        $warnings = $ctx->logger->recordsWithLevel(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertSame('orders', $warnings[0]['context']['source'] ?? null);
        self::assertInstanceOf(EventFetchException::class, $warnings[0]['context']['exception'] ?? null);
    }

    #[Test]
    public function itWalksAllSourcesInRoundRobinOrder(): void
    {
        $a = new TestSource('alpha');
        $b = new TestSource('bravo');
        $c = new TestSource('charlie');

        $ctx = new LoaderContext(
            sources: [$a, $b, $c],
            fetcher: CallableEventFetcher::alwaysEmpty(),
        );

        $ctx->loader->runOnce();

        self::assertSame(
            ['alpha', 'bravo', 'charlie'],
            array_map(static fn (array $call): string => $call['source']->name, $ctx->fetcher->calls),
            'sources must be visited in declared order',
        );
        self::assertSame(['alpha', 'bravo', 'charlie'], $ctx->gate->calls);
        self::assertSame(
            ['event-loader:source:alpha', 'event-loader:source:bravo', 'event-loader:source:charlie'],
            $ctx->lockFactory->released,
        );
    }

    #[Test]
    public function itPassesTheStoredCursorAsAfterIdToTheFetcher(): void
    {
        $source = new TestSource('orders');
        $store = new InMemoryEventStore();
        $store->stored['orders'] = [new Event(10, []), new Event(42, [])];

        $fetcher = CallableEventFetcher::alwaysEmpty();
        $ctx = new LoaderContext(sources: [$source], fetcher: $fetcher, store: $store);

        $ctx->loader->runOnce();

        self::assertCount(1, $fetcher->calls);
        self::assertSame(42, $fetcher->calls[0]['afterId'], 'cursor must equal the largest stored id');
        self::assertSame(RoundRobinEventLoader::DEFAULT_BATCH_SIZE, $fetcher->calls[0]['limit']);
    }

    #[Test]
    public function itReleasesTheLockEvenWhenTheStoreThrows(): void
    {
        $store = new InMemoryEventStore();
        $store->failAppendOnce(new \RuntimeException('db down'));

        $ctx = new LoaderContext(
            fetcher: CallableEventFetcher::alwaysReturns([new Event(1, [])]),
            store: $store,
        );

        try {
            $ctx->loader->runOnce();
            self::fail('store exception must bubble up - it is a hard error');
        } catch (\RuntimeException $e) {
            self::assertSame('db down', $e->getMessage());
        }

        self::assertSame(
            ['event-loader:source:orders'],
            $ctx->lockFactory->released,
            'finally{} must release the lock regardless of the exception source',
        );
    }

    /**
     * @param list<AbstractSource>|null $sources
     */
    private function buildLoader(
        ?array $sources = null,
        int $batchSize = RoundRobinEventLoader::DEFAULT_BATCH_SIZE,
    ): RoundRobinEventLoader {
        return new RoundRobinEventLoader(
            sources: $sources ?? [new TestSource('orders')],
            fetcher: CallableEventFetcher::alwaysEmpty(),
            store: new InMemoryEventStore(),
            lockFactory: new RecordingLockFactory(),
            gate: new ToggleSourceGate(),
            logger: new ArrayLogger(),
            batchSize: $batchSize,
        );
    }
}

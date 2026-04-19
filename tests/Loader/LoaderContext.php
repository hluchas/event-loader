<?php

declare(strict_types=1);

namespace App\Tests\Loader;

use App\Domain\AbstractSource;
use App\Loader\RoundRobinEventLoader;
use App\Tests\Support\ArrayLogger;
use App\Tests\Support\CallableEventFetcher;
use App\Tests\Support\InMemoryEventStore;
use App\Tests\Support\RecordingLockFactory;
use App\Tests\Support\TestSource;
use App\Tests\Support\ToggleSourceGate;

/**
 * Bundle of a {@see RoundRobinEventLoader} under test together with its
 * collaborators. Each collaborator is a public property so a test can
 * assert on recorded interactions without a chain of getters.
 */
final class LoaderContext
{
    public readonly RoundRobinEventLoader $loader;
    public readonly CallableEventFetcher $fetcher;
    public readonly InMemoryEventStore $store;
    public readonly RecordingLockFactory $lockFactory;
    public readonly ToggleSourceGate $gate;
    public readonly ArrayLogger $logger;

    /**
     * @param list<AbstractSource>|null $sources
     */
    public function __construct(
        ?array $sources = null,
        ?CallableEventFetcher $fetcher = null,
        ?InMemoryEventStore $store = null,
    ) {
        $this->fetcher = $fetcher ?? CallableEventFetcher::alwaysEmpty();
        $this->store = $store ?? new InMemoryEventStore();
        $this->lockFactory = new RecordingLockFactory();
        $this->gate = new ToggleSourceGate();
        $this->logger = new ArrayLogger();

        $this->loader = new RoundRobinEventLoader(
            sources: $sources ?? [new TestSource('orders')],
            fetcher: $this->fetcher,
            store: $this->store,
            lockFactory: $this->lockFactory,
            gate: $this->gate,
            logger: $this->logger,
        );
    }
}

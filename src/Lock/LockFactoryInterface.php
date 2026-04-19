<?php

declare(strict_types=1);

namespace App\Lock;

/**
 * Factory for {@see LockInterface} instances.
 *
 * The factory is the single abstraction that a concrete backend (Redis,
 * Postgres, Flock, …) needs to implement.
 */
interface LockFactoryInterface
{
    /**
     * Create a new lock handle for the given key with the given TTL in seconds.
     *
     * The TTL is a safety net against a crashed owner holding the lock forever:
     * the lock must auto-expire after $ttlSeconds even without an explicit
     * {@see LockInterface::release()}.
     *
     * The returned object is NOT yet acquired - the caller must call
     * {@see LockInterface::tryAcquire()}.
     */
    public function createLock(string $key, float $ttlSeconds): LockInterface;
}

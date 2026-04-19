<?php

declare(strict_types=1);

namespace App\Lock;

/**
 * A distributed mutex.
 *
 * Instances are obtained from a {@see LockFactoryInterface}. One
 * {@see LockInterface} represents one logical key in one logical ownership
 * cycle (acquire once, release once).
 */
interface LockInterface
{
    /**
     * Try to acquire the lock without blocking.
     *
     * Implementations MUST be atomic across all loader instances
     * (e.g. Redis `SET key value NX PX ttl`, Postgres advisory lock).
     *
     * @return bool true if the caller now owns the lock
     */
    public function tryAcquire(): bool;

    /**
     * Release the lock. Safe to call even if the lock was not acquired
     * or has already expired; the implementation must silently ignore such cases.
     */
    public function release(): void;
}

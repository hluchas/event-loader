<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Lock\LockFactoryInterface;
use App\Lock\LockInterface;

/**
 * Lock factory test double that records every lifecycle event (create,
 * tryAcquire success, release) and lets a test selectively deny acquisition
 * for specific keys to simulate contention with another loader instance.
 */
final class RecordingLockFactory implements LockFactoryInterface
{
    /** @var list<string> */
    public array $created = [];

    /** @var list<string> */
    public array $acquired = [];

    /** @var list<string> */
    public array $released = [];

    /** @var array<string, true> */
    private array $deniedKeys = [];

    public function deny(string $key): void
    {
        $this->deniedKeys[$key] = true;
    }

    public function createLock(string $key, float $ttlSeconds): LockInterface
    {
        $this->created[] = $key;

        return new RecordingLock($key, !isset($this->deniedKeys[$key]), $this);
    }

    /** @internal exposed only for {@see RecordingLock} */
    public function markAcquired(string $key): void
    {
        $this->acquired[] = $key;
    }

    /** @internal exposed only for {@see RecordingLock} */
    public function markReleased(string $key): void
    {
        $this->released[] = $key;
    }
}

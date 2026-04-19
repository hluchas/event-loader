<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Port\LockInterface;

/**
 * @internal companion to {@see RecordingLockFactory}
 */
final class RecordingLock implements LockInterface
{
    private bool $held = false;

    public function __construct(
        private readonly string $key,
        private readonly bool $canAcquire,
        private readonly RecordingLockFactory $recorder,
    ) {
    }

    public function tryAcquire(): bool
    {
        if (!$this->canAcquire) {
            return false;
        }

        $this->held = true;
        $this->recorder->markAcquired($this->key);

        return true;
    }

    public function release(): void
    {
        if (!$this->held) {
            return;
        }

        $this->held = false;
        $this->recorder->markReleased($this->key);
    }
}

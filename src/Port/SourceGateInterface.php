<?php

declare(strict_types=1);

namespace App\Port;

use App\Domain\AbstractSource;

/**
 * Global rate limiter enforcing a minimum interval between consecutive
 * requests to the same source, across ALL loader instances.
 *
 * Typical implementation: Redis `SET key 1 NX PX 200` — returns OK only
 * if no other caller has reserved the slot in the last 200 ms.
 *
 * The minimum interval is configuration of the concrete implementation
 * (200 ms per the task requirements).
 */
interface SourceGateInterface
{
    /**
     * Try to claim a request slot for the source.
     *
     * If it returns true, the caller is cleared to make ONE request to the
     * source right now, and no other caller (any instance on any server)
     * will be granted another slot until the minimum interval elapses.
     *
     * If it returns false, the caller must not hit the source and should
     * move on to another one.
     *
     * Implementations MUST be atomic across all loader instances.
     */
    public function reserve(AbstractSource $source): bool;
}

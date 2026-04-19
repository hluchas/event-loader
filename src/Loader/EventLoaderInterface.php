<?php

declare(strict_types=1);

namespace App\Loader;

/**
 * The main event loading loop.
 *
 * An implementation orchestrates fetchers and the store, enforces the
 * no-duplicate and rate-limit rules, and keeps running until stopped
 * externally (e.g. SIGTERM).
 */
interface EventLoaderInterface
{
    /**
     * Run the loader. This method blocks indefinitely, driving a round-robin
     * loop over all configured sources. It returns only when the loader is
     * asked to stop.
     */
    public function run(): void;
}

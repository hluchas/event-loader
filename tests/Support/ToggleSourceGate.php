<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Source\AbstractSource;
use App\SourceGate\SourceGateInterface;

/**
 * Source gate test double. Grants every request by default; a test can
 * selectively deny specific sources to simulate "200ms not elapsed yet".
 */
final class ToggleSourceGate implements SourceGateInterface
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, true> */
    private array $deniedSources = [];

    public function deny(string $sourceName): void
    {
        $this->deniedSources[$sourceName] = true;
    }

    public function reserve(AbstractSource $source): bool
    {
        $this->calls[] = $source->name;

        return !isset($this->deniedSources[$source->name]);
    }
}

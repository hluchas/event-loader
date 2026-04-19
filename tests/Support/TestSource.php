<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\AbstractSource;

/**
 * Concrete {@see AbstractSource} for tests. The production code never
 * instantiates {@see AbstractSource} directly (it is abstract), so tests
 * need a nameable subtype.
 */
final class TestSource extends AbstractSource
{
}

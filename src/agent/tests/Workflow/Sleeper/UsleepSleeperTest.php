<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Sleeper;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Workflow\Sleeper\UsleepSleeper;

final class UsleepSleeperTest extends TestCase
{
    public function testSleepWithZeroIsNoOp()
    {
        $sleeper = new UsleepSleeper();

        // Must not throw and must return quickly (no actual sleep when ms = 0)
        $sleeper->sleep(0);

        $this->addToAssertionCount(1);
    }

    public function testSleepWithPositiveValueReturnsWithoutError()
    {
        $sleeper = new UsleepSleeper();

        // 1 ms is negligible in tests; we just verify no exception is thrown
        $sleeper->sleep(1);

        $this->addToAssertionCount(1);
    }
}

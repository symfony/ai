<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Execution\ExecutionStatus;

final class ExecutionStatusTest extends TestCase
{
    public function testIsFinished()
    {
        $this->assertFalse(ExecutionStatus::Pending->isFinished());
        $this->assertFalse(ExecutionStatus::Running->isFinished());
        $this->assertFalse(ExecutionStatus::WaitingForInput->isFinished());
        $this->assertTrue(ExecutionStatus::Completed->isFinished());
        $this->assertTrue(ExecutionStatus::Failed->isFinished());
        $this->assertTrue(ExecutionStatus::Cancelled->isFinished());
    }
}

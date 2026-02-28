<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Step;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Workflow\Step\Step;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Platform\Result\ResultInterface;

final class StepTest extends TestCase
{
    public function testConstruction(): void
    {
        $executor = fn ($agent, $state) => $this->createMock(ResultInterface::class);
        $step = new Step('test-step', $executor);

        $this->assertSame('test-step', $step->getName());
        $this->assertFalse($step->isParallel());
        $this->assertSame(3, $step->getRetryCount());
        $this->assertSame(1000, $step->getRetryDelay());
    }

    public function testExecuteSuccess(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $executor = static fn ($agent, $state) => $result;

        $step = new Step('test-step', $executor);
        $agent = $this->createMock(AgentInterface::class);
        $state = new WorkflowState('id', 'test-step');

        $actualResult = $step->execute($agent, $state);

        $this->assertSame($result, $actualResult);
    }

    public function testExecuteWithRetry(): void
    {
        $attempts = 0;
        $result = $this->createMock(ResultInterface::class);
        $executor = static function ($agent, $state) use (&$attempts, $result) {
            ++$attempts;
            if ($attempts < 3) {
                throw new \RuntimeException('Temporary failure');
            }

            return $result;
        };

        $step = new Step('test-step', $executor, retryCount: 3, retryDelay: 1);
        $agent = $this->createMock(AgentInterface::class);
        $state = new WorkflowState('id', 'test-step');

        $actualResult = $step->execute($agent, $state);

        $this->assertSame(3, $attempts);
        $this->assertInstanceOf(ResultInterface::class, $actualResult);
    }

    public function testExecuteFailsAfterMaxRetries(): void
    {
        $executor = static function () {
            throw new \RuntimeException('Persistent failure');
        };

        $step = new Step('test-step', $executor, retryCount: 2, retryDelay: 1);
        $agent = $this->createMock(AgentInterface::class);
        $state = new WorkflowState('id', 'test-step');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Step "test-step" failed after 2 attempts');

        $step->execute($agent, $state);
    }

    public function testParallelStep(): void
    {
        $executor = fn () => $this->createMock(ResultInterface::class);
        $step = new Step('test-step', $executor, parallel: true);

        $this->assertTrue($step->isParallel());
    }

    public function testCustomRetryConfiguration(): void
    {
        $executor = fn () => $this->createMock(ResultInterface::class);
        $step = new Step('test-step', $executor, retryCount: 5, retryDelay: 2000);

        $this->assertSame(5, $step->getRetryCount());
        $this->assertSame(2000, $step->getRetryDelay());
    }
}

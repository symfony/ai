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
use Symfony\AI\Agent\Workflow\Step\SequentialStepExecutor;
use Symfony\AI\Agent\Workflow\Step\Step;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Platform\Result\ResultInterface;

final class SequentialStepExecutorTest extends TestCase
{
    public function testSupportsParallel(): void
    {
        $executor = new SequentialStepExecutor();

        $this->assertFalse($executor->supportsParallel());
    }

    public function testExecuteMultipleSteps(): void
    {
        $executor = new SequentialStepExecutor();
        $agent = $this->createMock(AgentInterface::class);
        $state = new WorkflowState('id', 'start');

        $result1 = $this->createMock(ResultInterface::class);
        $result2 = $this->createMock(ResultInterface::class);

        $steps = [
            new Step('step1', static fn (): ResultInterface => $result1),
            new Step('step2', static fn (): ResultInterface => $result2),
        ];

        $results = $executor->execute($steps, $agent, $state);

        $this->assertCount(2, $results);
        $this->assertSame($result1, $results[0]);
        $this->assertSame($result2, $results[1]);
    }

    public function testExecuteSingleStep(): void
    {
        $executor = new SequentialStepExecutor();
        $agent = $this->createMock(AgentInterface::class);
        $state = new WorkflowState('id', 'start');

        $result = $this->createMock(ResultInterface::class);
        $steps = [new Step('step1', static fn () => $result)];

        $results = $executor->execute($steps, $agent, $state);

        $this->assertCount(1, $results);
        $this->assertSame($result, $results[0]);
    }

    public function testExecutePreservesOrder(): void
    {
        $executor = new SequentialStepExecutor();
        $agent = $this->createMock(AgentInterface::class);
        $state = new WorkflowState('id', 'start');

        $executionOrder = [];
        $result = $this->createMock(ResultInterface::class);

        $steps = [
            new Step('step1', static function () use (&$executionOrder, $result) {
                $executionOrder[] = 'step1';

                return $result;
            }),
            new Step('step2', static function () use (&$executionOrder, $result) {
                $executionOrder[] = 'step2';

                return $result;
            }),
            new Step('step3', static function () use (&$executionOrder, $result) {
                $executionOrder[] = 'step3';

                return $result;
            }),
        ];

        $executor->execute($steps, $agent, $state);

        $this->assertSame(['step1', 'step2', 'step3'], $executionOrder);
    }
}

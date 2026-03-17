<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Executor;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\WorkflowExecutorException;
use Symfony\AI\Agent\Workflow\Executor\FiberExecutor;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

final class FiberExecutorTest extends TestCase
{
    public function testExecuteSimpleCallback()
    {
        $executor = new FiberExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
            return $state->set('result', 'done at '.$place);
        });

        $state = new WorkflowState('id');
        $result = $executor->execute($state, 'my_place');

        $this->assertSame('done at my_place', $result->get('result'));
    }

    public function testExecuteWithFiberSuspend()
    {
        $executor = new FiberExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
            $state->set('step1', 'done');
            \Fiber::suspend();
            $state->set('step2', 'done');

            return $state;
        });

        $state = new WorkflowState('id');
        $result = $executor->execute($state, 'place');

        $this->assertSame('done', $result->get('step1'));
        $this->assertSame('done', $result->get('step2'));
    }

    public function testExecuteWrapsException()
    {
        $executor = new FiberExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
            throw new \RuntimeException('Fiber failed');
        });

        $this->expectException(WorkflowExecutorException::class);
        $this->expectExceptionMessage('Fiber execution failed at place "place": "Fiber failed".');

        $executor->execute(new WorkflowState('id'), 'place');
    }
}

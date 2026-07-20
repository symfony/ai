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
use Symfony\AI\Agent\Workflow\Executor\CallableExecutor;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

final class CallableExecutorTest extends TestCase
{
    public function testExecuteInvokesCallback()
    {
        $executor = new CallableExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
            return $state->set('result', 'done at '.$place);
        });

        $result = $executor->execute(new WorkflowState('id'), 'my_place');

        $this->assertSame('done at my_place', $result->get('result'));
    }

    public function testExecuteWrapsException()
    {
        $executor = new CallableExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
            throw new \RuntimeException('callback failed');
        });

        $this->expectException(WorkflowExecutorException::class);
        $this->expectExceptionMessage('Callable execution failed at place "place": "callback failed".');

        $executor->execute(new WorkflowState('id'), 'place');
    }
}

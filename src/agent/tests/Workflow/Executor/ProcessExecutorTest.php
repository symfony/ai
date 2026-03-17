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
use Symfony\AI\Agent\Workflow\Executor\ProcessExecutor;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

final class ProcessExecutorTest extends TestCase
{
    public function testExecuteWithStaticCommand()
    {
        $executor = new ProcessExecutor(
            command: ['echo', 'hello world'],
            outputKey: 'output',
        );

        $state = new WorkflowState('id');
        $result = $executor->execute($state, 'place');

        $this->assertSame("hello world\n", $result->get('output'));
    }

    public function testExecuteWithClosureCommand()
    {
        $executor = new ProcessExecutor(
            command: static fn (WorkflowStateInterface $state, string $place): array => ['echo', $state->get('message')],
            outputKey: 'output',
        );

        $state = new WorkflowState('id', ['message' => 'dynamic']);
        $result = $executor->execute($state, 'place');

        $this->assertSame("dynamic\n", $result->get('output'));
    }

    public function testExecuteThrowsOnFailedProcess()
    {
        $executor = new ProcessExecutor(
            command: ['false'],
            outputKey: 'output',
        );

        $this->expectException(WorkflowExecutorException::class);
        $this->expectExceptionMessage('Process execution failed at place "place"');

        $executor->execute(new WorkflowState('id'), 'place');
    }
}

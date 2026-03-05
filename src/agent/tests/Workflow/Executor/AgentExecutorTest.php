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
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\WorkflowExecutorException;
use Symfony\AI\Agent\Workflow\Executor\AgentExecutor;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class AgentExecutorTest extends TestCase
{
    public function testExecuteWithStringInput()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Generated text');

        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())
            ->method('call')
            ->with($this->isInstanceOf(MessageBag::class))
            ->willReturn($result);

        $executor = new AgentExecutor($agent, inputKey: 'prompt', outputKey: 'result');
        $state = new WorkflowState('id', ['prompt' => 'Write something']);

        $newState = $executor->execute($state, 'draft');

        $this->assertSame('Generated text', $newState->get('result'));
    }

    public function testExecuteWithMessageBagInput()
    {
        $messageBag = new MessageBag();
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Response');

        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())
            ->method('call')
            ->with($messageBag)
            ->willReturn($result);

        $executor = new AgentExecutor($agent);
        $state = new WorkflowState('id', ['input' => $messageBag]);

        $newState = $executor->execute($state, 'place');

        $this->assertSame('Response', $newState->get('output'));
    }

    public function testExecuteThrowsOnInvalidInputType()
    {
        $agent = $this->createMock(AgentInterface::class);
        $executor = new AgentExecutor($agent);
        $state = new WorkflowState('id', ['input' => 42]);

        $this->expectException(WorkflowExecutorException::class);
        $this->expectExceptionMessage('AgentExecutor expects state key "input" to contain a string or MessageBag');

        $executor->execute($state, 'place');
    }

    public function testExecuteWrapsAgentException()
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('call')->willThrowException(new \RuntimeException('API error'));

        $executor = new AgentExecutor($agent);
        $state = new WorkflowState('id', ['input' => 'test']);

        $this->expectException(WorkflowExecutorException::class);
        $this->expectExceptionMessage('Agent execution failed at place "draft": API error');

        $executor->execute($state, 'draft');
    }
}

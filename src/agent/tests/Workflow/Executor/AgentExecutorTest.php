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
use Symfony\AI\Agent\DeferrableAgentInterface;
use Symfony\AI\Agent\DeferredAgentCall;
use Symfony\AI\Agent\Exception\WorkflowExecutorException;
use Symfony\AI\Agent\Workflow\Executor\AgentExecutor;
use Symfony\AI\Agent\Workflow\PendingExecution;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;

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

    public function testExecuteCapturesMetadataWhenKeyConfigured()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Generated text');
        $result->method('getMetadata')->willReturn(new Metadata(['tokens' => 42]));

        $agent = $this->createMock(AgentInterface::class);
        $agent->method('call')->willReturn($result);

        $executor = new AgentExecutor($agent, inputKey: 'prompt', outputKey: 'result', metadataKey: 'meta');
        $newState = $executor->execute(new WorkflowState('id', ['prompt' => 'Write something']), 'draft');

        $this->assertSame('Generated text', $newState->get('result'));
        $this->assertSame(['tokens' => 42], $newState->get('meta'));
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
        $this->expectExceptionMessage('Agent execution failed at place "draft": "API error".');

        $executor->execute($state, 'draft');
    }

    public function testExecuteWritesResultUnderConfiguredOutputKey()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Custom output');

        $agent = $this->createMock(AgentInterface::class);
        $agent->method('call')->willReturn($result);

        $executor = new AgentExecutor($agent, inputKey: 'q', outputKey: 'answer');
        $state = new WorkflowState('id', ['q' => 'What is 2+2?']);

        $newState = $executor->execute($state, 'compute');

        $this->assertSame('Custom output', $newState->get('answer'));
    }

    public function testMetadataIsNotWrittenWhenKeyIsNull()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('text');

        $agent = $this->createMock(AgentInterface::class);
        $agent->method('call')->willReturn($result);

        // metadataKey defaults to null
        $executor = new AgentExecutor($agent, inputKey: 'input', outputKey: 'output');
        $state = new WorkflowState('id', ['input' => 'hello']);

        $newState = $executor->execute($state, 'place');

        $this->assertFalse($newState->has('meta'));
    }

    public function testStaticOptionsArePassedToAgentCall()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('ok');

        $capturedOptions = [];
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (MessageBag $messages, array $options) use ($result, &$capturedOptions): ResultInterface {
                $capturedOptions = $options;

                return $result;
            });

        $executor = new AgentExecutor(
            $agent,
            inputKey: 'input',
            outputKey: 'output',
            options: ['model' => 'gpt-4o', 'temperature' => 0.5],
        );

        $executor->execute(new WorkflowState('id', ['input' => 'hi']), 'place');

        $this->assertSame('gpt-4o', $capturedOptions['model']);
        $this->assertSame(0.5, $capturedOptions['temperature']);
    }

    public function testOptionsKeyMergesStateOptionsOverStaticOptions()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('ok');

        $capturedOptions = [];
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (MessageBag $messages, array $options) use ($result, &$capturedOptions): ResultInterface {
                $capturedOptions = $options;

                return $result;
            });

        $executor = new AgentExecutor(
            $agent,
            inputKey: 'input',
            outputKey: 'output',
            options: ['temperature' => 0.5, 'max_tokens' => 100],
            optionsKey: 'run_opts',
        );

        $state = new WorkflowState('id', [
            'input' => 'prompt',
            'run_opts' => ['temperature' => 0.9, 'stream' => true],
        ]);

        $executor->execute($state, 'place');

        // State options override static; non-overridden keys are kept
        $this->assertSame(0.9, $capturedOptions['temperature']);
        $this->assertSame(100, $capturedOptions['max_tokens']);
        $this->assertTrue($capturedOptions['stream']);
    }

    public function testOptionsKeyFallsBackToStaticOptionsWhenStateValueIsNotAnArray()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('ok');

        $capturedOptions = [];
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (MessageBag $messages, array $options) use ($result, &$capturedOptions): ResultInterface {
                $capturedOptions = $options;

                return $result;
            });

        $executor = new AgentExecutor(
            $agent,
            inputKey: 'input',
            outputKey: 'output',
            options: ['temperature' => 0.7],
            optionsKey: 'run_opts',
        );

        // 'run_opts' is not set in state
        $state = new WorkflowState('id', ['input' => 'prompt']);

        $executor->execute($state, 'place');

        $this->assertSame(0.7, $capturedOptions['temperature']);
    }

    public function testHistoryKeyAccumulatesConversationAcrossPlaces()
    {
        // First turn: no history yet
        $result1 = $this->createMock(ResultInterface::class);
        $result1->method('getContent')->willReturn('Hello back!');

        $agent = $this->createMock(AgentInterface::class);
        $agent->method('call')->willReturn($result1);

        $executor = new AgentExecutor(
            $agent,
            inputKey: 'input',
            outputKey: 'output',
            historyKey: 'history',
        );

        $state = new WorkflowState('id', ['input' => 'Hello!']);
        $state = $executor->execute($state, 'turn_1');

        /** @var MessageBag $history */
        $history = $state->get('history');
        $this->assertInstanceOf(MessageBag::class, $history);
        // user message + assistant message
        $this->assertCount(2, $history);
    }

    public function testHistoryKeyPrependsExistingHistoryToNewInput()
    {
        $existingHistory = new MessageBag(
            Message::ofUser('First user message'),
            Message::ofAssistant('First assistant reply'),
        );

        $capturedBag = null;
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Second assistant reply');

        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (MessageBag $messages) use ($result, &$capturedBag): ResultInterface {
                $capturedBag = $messages;

                return $result;
            });

        $executor = new AgentExecutor(
            $agent,
            inputKey: 'input',
            outputKey: 'output',
            historyKey: 'history',
        );

        $state = new WorkflowState('id', [
            'input' => 'Second user message',
            'history' => $existingHistory,
        ]);

        $executor->execute($state, 'turn_2');

        $this->assertNotNull($capturedBag);
        // history (2) + new input (1) = 3 messages passed to agent
        $this->assertCount(3, $capturedBag);
    }

    public function testHistoryKeyThrowsWhenStateValueIsNotMessageBag()
    {
        $agent = $this->createMock(AgentInterface::class);

        $executor = new AgentExecutor(
            $agent,
            inputKey: 'input',
            outputKey: 'output',
            historyKey: 'history',
        );

        $state = new WorkflowState('id', ['input' => 'hi', 'history' => 'not-a-bag']);

        $this->expectException(WorkflowExecutorException::class);
        $this->expectExceptionMessage('AgentExecutor expects state key "history" to contain a MessageBag');

        $executor->execute($state, 'place');
    }

    public function testDispatchReturnsNullHandleForNonDeferrableAgent()
    {
        $agent = $this->createMock(AgentInterface::class);

        $executor = new AgentExecutor($agent);
        $state = new WorkflowState('id', ['input' => 'prompt']);

        $pending = $executor->dispatch($state, 'place');

        $this->assertInstanceOf(PendingExecution::class, $pending);
        $this->assertNull($pending->handle);
    }

    public function testSettleWithNullHandleFallsBackToExecute()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('sync result');

        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())
            ->method('call')
            ->willReturn($result);

        $executor = new AgentExecutor($agent, inputKey: 'input', outputKey: 'output');
        $state = new WorkflowState('id', ['input' => 'prompt']);
        $pending = new PendingExecution(null);

        $newState = $executor->settle($state, 'place', $pending);

        $this->assertSame('sync result', $newState->get('output'));
    }

    public function testDispatchWithDeferrableAgentReturnsDeferredAgentCallHandle()
    {
        $innerResult = $this->createMock(ResultInterface::class);
        $deferredResult = new DeferredResult(new PlainConverter($innerResult), new InMemoryRawResult());
        $messages = new MessageBag(Message::ofUser('async prompt'));
        $deferredCall = new DeferredAgentCall($deferredResult, 'gpt-4o', $messages);

        $agent = $this->createMock(DeferrableAgentInterface::class);
        $agent->expects($this->once())
            ->method('prepare')
            ->with($this->isInstanceOf(MessageBag::class))
            ->willReturn($deferredCall);

        $executor = new AgentExecutor($agent, inputKey: 'input', outputKey: 'output');
        $state = new WorkflowState('id', ['input' => 'async prompt']);

        $pending = $executor->dispatch($state, 'async_place');

        $this->assertInstanceOf(PendingExecution::class, $pending);
        $this->assertSame($deferredCall, $pending->handle);
    }

    public function testSettleWithDeferredAgentCallFinishesAndWritesOutput()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('async result');

        $messages = new MessageBag(Message::ofUser('async prompt'));
        $deferredResult = new DeferredResult(new PlainConverter($result), new InMemoryRawResult());
        $deferredCall = new DeferredAgentCall($deferredResult, 'gpt-4o', $messages);

        $agent = $this->createMock(DeferrableAgentInterface::class);
        $agent->expects($this->once())
            ->method('finish')
            ->with($deferredCall)
            ->willReturn($result);

        $executor = new AgentExecutor($agent, inputKey: 'input', outputKey: 'output');
        $state = new WorkflowState('id', ['input' => 'async prompt']);
        $pending = new PendingExecution($deferredCall);

        $newState = $executor->settle($state, 'async_place', $pending);

        $this->assertSame('async result', $newState->get('output'));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\MultiAgent;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\MultiAgent\Handoff;
use Symfony\AI\Agent\MultiAgent\Handoff\Decision;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\AI\Agent\Tests\Fixtures\SuspendingConverter;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class MultiAgentTest extends TestCase
{
    public function testConstructorThrowsExceptionForEmptyHandoffs()
    {
        $orchestrator = new MockAgent(name: 'orchestrator');
        $fallback = new MockAgent(name: 'fallback');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MultiAgent requires at least 1 handoff.');

        new MultiAgent($orchestrator, [], $fallback);
    }

    public function testGetName()
    {
        $orchestrator = new MockAgent(name: 'orchestrator');
        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical', 'coding']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback, 'custom-multi-agent');

        $this->assertSame('custom-multi-agent', $multiAgent->getName());
    }

    public function testGetNameWithDefaultName()
    {
        $orchestrator = new MockAgent(name: 'orchestrator');
        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $this->assertSame('multi-agent', $multiAgent->getName());
    }

    public function testCallThrowsExceptionWhenNoUserMessage()
    {
        $orchestrator = new MockAgent(name: 'orchestrator');
        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(new SystemMessage('System prompt'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No user message found in conversation.');

        $multiAgent->call($messages)->getResult();
    }

    public function testCallDelegatesToSelectedAgent()
    {
        $decision = new Decision('technical', 'This is a technical question');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn($this->execution($orchestratorResult));

        $expectedResult = new TextResult('Technical response');
        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->method('call')->willReturn($this->execution($expectedResult));

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff($technicalAgent, ['technical', 'coding']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('How do I implement a function?'));

        $result = $multiAgent->call($messages)->getResult();

        $this->assertSame($expectedResult, $result);
    }

    public function testCallUsesOrchestratorWhenDecisionIsNotReturned()
    {
        // Create a mock result that returns a non-Decision content
        $firstResult = $this->createMock(ResultInterface::class);
        $firstResult->method('getContent')->willReturn('Not a Decision object');

        $expectedResult = new TextResult('Orchestrator response');
        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')
            ->willReturnOnConsecutiveCalls(
                $this->execution($firstResult),
                $this->execution($expectedResult),
            );

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('Hello'));

        $result = $multiAgent->call($messages)->getResult();

        $this->assertSame($expectedResult, $result);
    }

    public function testCallUsesFallbackWhenNoAgentSelected()
    {
        $decision = new Decision('', 'No specific agent matches');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn($this->execution($orchestratorResult));

        $expectedResult = new TextResult('Fallback response');
        $fallback = $this->createMock(AgentInterface::class);
        $fallback->method('getName')->willReturn('fallback');
        $fallback->method('call')->willReturn($this->execution($expectedResult));

        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('General question'));

        $result = $multiAgent->call($messages)->getResult();

        $this->assertSame($expectedResult, $result);
    }

    public function testCallUsesFallbackWhenTargetAgentNotFound()
    {
        $decision = new Decision('nonexistent', 'Selected non-existent agent');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn($this->execution($orchestratorResult));

        $expectedResult = new TextResult('Fallback response');
        $fallback = $this->createMock(AgentInterface::class);
        $fallback->method('getName')->willReturn('fallback');
        $fallback->method('call')->willReturn($this->execution($expectedResult));

        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('Question'));

        $result = $multiAgent->call($messages)->getResult();

        $this->assertSame($expectedResult, $result);
    }

    public function testCallWithMultipleHandoffs()
    {
        $decision = new Decision('creative', 'This is a creative task');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn($this->execution($orchestratorResult));

        $technicalAgent = new MockAgent(name: 'technical');
        $expectedResult = new TextResult('Creative response');
        $creativeAgent = $this->createMock(AgentInterface::class);
        $creativeAgent->method('getName')->willReturn('creative');
        $creativeAgent->method('call')->willReturn($this->execution($expectedResult));

        $fallback = new MockAgent(name: 'fallback');

        $handoffs = [
            new Handoff($technicalAgent, ['technical', 'coding']),
            new Handoff($creativeAgent, ['creative', 'writing']),
        ];

        $multiAgent = new MultiAgent($orchestrator, $handoffs, $fallback);

        $messages = new MessageBag(Message::ofUser('Write a poem'));

        $result = $multiAgent->call($messages)->getResult();

        $this->assertSame($expectedResult, $result);
    }

    public function testCallPassesOptionsToAgents()
    {
        $options = ['temperature' => 0.7, 'max_tokens' => 100];

        $decision = new Decision('technical', 'Technical question');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        // Create a mock that verifies options are passed correctly
        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->expects($this->once())
            ->method('call')
            ->with(
                $this->isInstanceOf(MessageBag::class),
                $this->callback(static fn ($opts) => isset($opts['temperature']) && 0.7 === $opts['temperature']
                    && isset($opts['max_tokens']) && 100 === $opts['max_tokens']
                    && isset($opts['response_format']) && Decision::class === $opts['response_format']
                )
            )
            ->willReturn($this->execution($orchestratorResult));

        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->expects($this->once())
            ->method('call')
            ->with(
                $this->isInstanceOf(MessageBag::class),
                $options
            )
            ->willReturn($this->execution(new TextResult('Response')));

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff($technicalAgent, ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('Technical question'));

        $multiAgent->call($messages, $options)->getResult();
    }

    public function testCallWithLogging()
    {
        $logger = $this->createMock(LoggerInterface::class);

        // Expect 4 debug log messages
        $logger->expects($this->exactly(4))
            ->method('debug');

        $decision = new Decision('technical', 'Technical question');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn($this->execution($orchestratorResult));

        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->method('call')->willReturn($this->execution(new TextResult('Response')));

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff($technicalAgent, ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback, 'test', $logger);

        $messages = new MessageBag(Message::ofUser('Technical question'));

        $multiAgent->call($messages)->getResult();
    }

    public function testCallExtractsTextFromComplexUserMessage()
    {
        $decision = new Decision('technical', 'Technical question');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn($this->execution($orchestratorResult));

        $expectedResult = new TextResult('Technical response');
        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->method('call')->willReturn($this->execution($expectedResult));

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff($technicalAgent, ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        // Create a complex user message with multiple text parts
        $userMessage = new UserMessage(
            new Text('Part 1'),
            new Text('Part 2'),
        );

        $messages = new MessageBag($userMessage);

        $result = $multiAgent->call($messages)->getResult();

        $this->assertSame($expectedResult, $result);
    }

    public function testBuildAgentSelectionPromptIncludesFallback()
    {
        $decision = new Decision('', 'no agent matched');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->expects($this->once())
            ->method('call')
            ->with(
                $this->callback(static function (MessageBag $messages) {
                    $userMessage = $messages->getUserMessage();
                    $text = $userMessage?->asText();

                    return str_contains($text, 'general-fallback: fallback agent for general/unmatched queries');
                }),
                $this->anything()
            )
            ->willReturn($this->execution($orchestratorResult));

        $fallback = $this->createMock(AgentInterface::class);
        $fallback->method('getName')->willReturn('general-fallback');
        $fallback->method('call')->willReturn($this->execution(new TextResult('Fallback response')));

        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('Question'));

        $multiAgent->call($messages)->getResult();
    }

    public function testCancelPropagatesToTheOrchestratorExecution()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn(new DeferredResult(new SuspendingConverter(), new RawHttpResult($response)));

        $orchestrator = new Agent($platform, 'gpt-4');
        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], new MockAgent(name: 'fallback'));
        $execution = $multiAgent->call(new MessageBag(Message::ofUser('Question')));

        $fiber = new \Fiber(static fn (): ResultInterface => $execution->getResult());
        $fiber->start();

        $execution->cancel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The agent execution was canceled.');

        $fiber->resume();
    }

    public function testCallForwardsProgressUpdatesAndEmitsHandoff()
    {
        $decision = new Decision('technical', 'This is a technical question');

        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn(new Execution(static function () use ($orchestratorResult): \Generator {
            yield new Progress('model_request', 'Invoking orchestrator model.');
            yield new ResultUpdate($orchestratorResult);
        }));

        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->method('call')->willReturn(new Execution(static function (): \Generator {
            yield new Progress('tool_call', 'Executing tool.');
            yield new Progress('delta', 'Streaming answer.');
            yield new ResultUpdate(new TextResult('Technical response'));
        }));

        $fallback = $this->createMock(AgentInterface::class);
        $fallback->method('getName')->willReturn('fallback');

        $handoff = new Handoff($technicalAgent, ['technical']);
        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $updates = iterator_to_array($multiAgent->call(new MessageBag(Message::ofUser('Help with code'))));

        $this->assertCount(5, $updates);

        // 1. Orchestrator progress
        $this->assertInstanceOf(Progress::class, $updates[0]);
        $this->assertSame('model_request', $updates[0]->getStage());
        $this->assertSame('Invoking orchestrator model.', $updates[0]->getMessage());

        // 2. MultiAgent handoff progress
        $this->assertInstanceOf(Progress::class, $updates[1]);
        $this->assertSame('handoff', $updates[1]->getStage());
        $this->assertSame('Routing to agent "technical".', $updates[1]->getMessage());
        $this->assertSame($decision, $updates[1]->getPayload());

        // 3. Technical agent tool call progress
        $this->assertInstanceOf(Progress::class, $updates[2]);
        $this->assertSame('tool_call', $updates[2]->getStage());

        // 4. Technical agent delta progress
        $this->assertInstanceOf(Progress::class, $updates[3]);
        $this->assertSame('delta', $updates[3]->getStage());

        // 5. Final result update
        $this->assertInstanceOf(ResultUpdate::class, $updates[4]);
        $this->assertSame('Technical response', $updates[4]->getResult()->getContent());
    }

    public function testCallDropsTheOrchestratorDeltasButForwardsTheAnsweringOnes()
    {
        $decision = new Decision('technical', 'This is a technical question');

        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn(new Execution(static function () use ($orchestratorResult): \Generator {
            yield new Progress('model_request', 'Invoking orchestrator model.');
            // the routing round spells out the Decision, which is no part of the answer
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('{"agentName":"tech'));
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('nical"}'));
            yield new ResultUpdate($orchestratorResult);
        }));

        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->method('call')->willReturn(new Execution(static function (): \Generator {
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('Hello '));
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('world'));
            yield new ResultUpdate(new TextResult('Hello world'));
        }));

        $fallback = $this->createMock(AgentInterface::class);
        $fallback->method('getName')->willReturn('fallback');

        $handoff = new Handoff($technicalAgent, ['technical']);
        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $answer = '';
        foreach ($multiAgent->call(new MessageBag(Message::ofUser('Help with code')), ['stream' => true]) as $update) {
            if ($update instanceof Progress && 'delta' === $update->getStage() && $update->getPayload() instanceof TextDelta) {
                $answer .= $update->getPayload()->getText();
            }
        }

        $this->assertSame('Hello world', $answer);
    }

    #[DataProvider('provideFallbackDecisions')]
    public function testCallEmitsHandoffCarryingTheDecisionWhenFallingBack(Decision $decision)
    {
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn($this->execution($orchestratorResult));

        $fallback = $this->createMock(AgentInterface::class);
        $fallback->method('getName')->willReturn('fallback');
        $fallback->method('call')->willReturn($this->execution(new TextResult('Fallback response')));

        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);
        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $updates = iterator_to_array($multiAgent->call(new MessageBag(Message::ofUser('Question'))));

        $this->assertCount(2, $updates);

        // The handoff names the agent that actually runs, while its payload carries the orchestrator's decision
        $this->assertInstanceOf(Progress::class, $updates[0]);
        $this->assertSame('handoff', $updates[0]->getStage());
        $this->assertSame('Routing to fallback agent "fallback".', $updates[0]->getMessage());
        $this->assertSame($decision, $updates[0]->getPayload());

        $this->assertInstanceOf(ResultUpdate::class, $updates[1]);
        $this->assertSame('Fallback response', $updates[1]->getResult()->getContent());
    }

    /**
     * @return iterable<string, array{Decision}>
     */
    public static function provideFallbackDecisions(): iterable
    {
        yield 'no agent selected' => [new Decision('', 'No specific agent matches')];
        yield 'agent not found' => [new Decision('nonexistent', 'Selected non-existent agent')];
    }

    private function execution(ResultInterface $result): Execution
    {
        return new Execution(static function () use ($result): \Generator {
            yield new ResultUpdate($result);
        });
    }
}

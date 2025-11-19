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

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\MultiAgent\Handoff;
use Symfony\AI\Agent\MultiAgent\Handoff\Decision;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

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

        $multiAgent->call($messages);
    }

    public function testCallDelegatesToSelectedAgent()
    {
        $decision = new Decision('technical', 'This is a technical question');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn($orchestratorResult);

        $expectedResult = new TextResult('Technical response');
        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->method('call')->willReturn($expectedResult);

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff($technicalAgent, ['technical', 'coding']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('How do I implement a function?'));

        $result = $multiAgent->call($messages);

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
                $firstResult,
                $expectedResult
            );

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('Hello'));

        $result = $multiAgent->call($messages);

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
        $orchestrator->method('call')->willReturn($orchestratorResult);

        $expectedResult = new TextResult('Fallback response');
        $fallback = $this->createMock(AgentInterface::class);
        $fallback->method('getName')->willReturn('fallback');
        $fallback->method('call')->willReturn($expectedResult);

        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('General question'));

        $result = $multiAgent->call($messages);

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
        $orchestrator->method('call')->willReturn($orchestratorResult);

        $expectedResult = new TextResult('Fallback response');
        $fallback = $this->createMock(AgentInterface::class);
        $fallback->method('getName')->willReturn('fallback');
        $fallback->method('call')->willReturn($expectedResult);

        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('Question'));

        $result = $multiAgent->call($messages);

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
        $orchestrator->method('call')->willReturn($orchestratorResult);

        $technicalAgent = new MockAgent(name: 'technical');
        $expectedResult = new TextResult('Creative response');
        $creativeAgent = $this->createMock(AgentInterface::class);
        $creativeAgent->method('getName')->willReturn('creative');
        $creativeAgent->method('call')->willReturn($expectedResult);

        $fallback = new MockAgent(name: 'fallback');

        $handoffs = [
            new Handoff($technicalAgent, ['technical', 'coding']),
            new Handoff($creativeAgent, ['creative', 'writing']),
        ];

        $multiAgent = new MultiAgent($orchestrator, $handoffs, $fallback);

        $messages = new MessageBag(Message::ofUser('Write a poem'));

        $result = $multiAgent->call($messages);

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
                $this->callback(fn ($opts) => isset($opts['temperature']) && 0.7 === $opts['temperature']
                    && isset($opts['max_tokens']) && 100 === $opts['max_tokens']
                    && isset($opts['response_format']) && Decision::class === $opts['response_format']
                )
            )
            ->willReturn($orchestratorResult);

        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->expects($this->once())
            ->method('call')
            ->with(
                $this->isInstanceOf(MessageBag::class),
                $options
            )
            ->willReturn(new TextResult('Response'));

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff($technicalAgent, ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('Technical question'));

        $multiAgent->call($messages, $options);
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
        $orchestrator->method('call')->willReturn($orchestratorResult);

        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->method('call')->willReturn(new TextResult('Response'));

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff($technicalAgent, ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback, 'test', $logger);

        $messages = new MessageBag(Message::ofUser('Technical question'));

        $multiAgent->call($messages);
    }

    public function testCallExtractsTextFromComplexUserMessage()
    {
        $decision = new Decision('technical', 'Technical question');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->method('call')->willReturn($orchestratorResult);

        $expectedResult = new TextResult('Technical response');
        $technicalAgent = $this->createMock(AgentInterface::class);
        $technicalAgent->method('getName')->willReturn('technical');
        $technicalAgent->method('call')->willReturn($expectedResult);

        $fallback = new MockAgent(name: 'fallback');
        $handoff = new Handoff($technicalAgent, ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        // Create a complex user message with multiple text parts
        $userMessage = new UserMessage(
            new Text('Part 1'),
            new Text('Part 2'),
        );

        $messages = new MessageBag($userMessage);

        $result = $multiAgent->call($messages);

        $this->assertSame($expectedResult, $result);
    }

    public function testBuildAgentSelectionPromptIncludesFallback()
    {
        $decision = new Decision('');

        // Create a mock result that returns the Decision object
        $orchestratorResult = $this->createMock(ResultInterface::class);
        $orchestratorResult->method('getContent')->willReturn($decision);

        $orchestrator = $this->createMock(AgentInterface::class);
        $orchestrator->method('getName')->willReturn('orchestrator');
        $orchestrator->expects($this->once())
            ->method('call')
            ->with(
                $this->callback(function (MessageBag $messages) {
                    $userMessage = $messages->getUserMessage();
                    $text = $userMessage?->asText();

                    return str_contains($text, 'general-fallback: fallback agent for general/unmatched queries');
                }),
                $this->anything()
            )
            ->willReturn($orchestratorResult);

        $fallback = $this->createMock(AgentInterface::class);
        $fallback->method('getName')->willReturn('general-fallback');
        $fallback->method('call')->willReturn(new TextResult('Fallback response'));

        $handoff = new Handoff(new MockAgent(name: 'technical'), ['technical']);

        $multiAgent = new MultiAgent($orchestrator, [$handoff], $fallback);

        $messages = new MessageBag(Message::ofUser('Question'));

        $multiAgent->call($messages);
    }
}

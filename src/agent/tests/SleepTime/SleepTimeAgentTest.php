<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\SleepTime;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\SleepTime\MemoryBlock;
use Symfony\AI\Agent\SleepTime\SleepTimeAgent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class SleepTimeAgentTest extends TestCase
{
    public function testConstructorThrowsExceptionForEmptyMemoryBlocks()
    {
        $primaryAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent = $this->createMock(AgentInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SleepTimeAgent requires at least one memory block.');

        new SleepTimeAgent($primaryAgent, $sleepingAgent, []);
    }

    public function testConstructorThrowsExceptionForZeroFrequency()
    {
        $primaryAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent = $this->createMock(AgentInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sleep frequency must be at least 1, 0 given.');

        new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')], 0);
    }

    public function testConstructorThrowsExceptionForNegativeFrequency()
    {
        $primaryAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent = $this->createMock(AgentInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sleep frequency must be at least 1, -1 given.');

        new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')], -1);
    }

    public function testGetNameReturnsDefaultName()
    {
        $primaryAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent = $this->createMock(AgentInterface::class);

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')]);

        $this->assertSame('sleep-time-agent', $agent->getName());
    }

    public function testGetNameReturnsCustomName()
    {
        $primaryAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent = $this->createMock(AgentInterface::class);

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')], 5, 'custom-agent');

        $this->assertSame('custom-agent', $agent->getName());
    }

    public function testCallDelegatesToPrimaryAgent()
    {
        $expectedResult = new TextResult('Hello!');
        $messages = new MessageBag(Message::ofUser('Hi'));

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->expects($this->once())
            ->method('call')
            ->with($messages, [])
            ->willReturn($expectedResult);

        $sleepingAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent->expects($this->never())->method('call');

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')]);

        $result = $agent->call($messages);

        $this->assertSame($expectedResult, $result);
    }

    public function testCallPassesOptionsToThePrimaryAgent()
    {
        $options = ['temperature' => 0.7];
        $messages = new MessageBag(Message::ofUser('Hi'));
        $expectedResult = new TextResult('Hello!');

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->expects($this->once())
            ->method('call')
            ->with($messages, $options)
            ->willReturn($expectedResult);

        $sleepingAgent = $this->createMock(AgentInterface::class);

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')]);

        $result = $agent->call($messages, $options);

        $this->assertSame($expectedResult, $result);
    }

    public function testSleepPhaseTriggersAtCorrectFrequency()
    {
        $messages = new MessageBag(Message::ofUser('Hi'));
        $primaryResult = new TextResult('Hello!');

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->method('call')->willReturn($primaryResult);

        $sleepingAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent->expects($this->once())
            ->method('call')
            ->with($this->isInstanceOf(MessageBag::class));

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')], 3);

        // Calls 1, 2 should NOT trigger sleep
        $agent->call($messages);
        $agent->call($messages);

        // Call 3 SHOULD trigger sleep
        $agent->call($messages);
    }

    public function testSleepPhaseDoesNotTriggerBeforeFrequency()
    {
        $messages = new MessageBag(Message::ofUser('Hi'));
        $primaryResult = new TextResult('Hello!');

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->method('call')->willReturn($primaryResult);

        $sleepingAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent->expects($this->never())->method('call');

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')], 5);

        // Only 4 calls - should not trigger sleep (frequency = 5)
        $agent->call($messages);
        $agent->call($messages);
        $agent->call($messages);
        $agent->call($messages);
    }

    public function testSleepPhaseTriggersMultipleTimes()
    {
        $messages = new MessageBag(Message::ofUser('Hi'));
        $primaryResult = new TextResult('Hello!');

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->method('call')->willReturn($primaryResult);

        $sleepingAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent->expects($this->exactly(2))
            ->method('call');

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')], 2);

        // 4 calls with frequency 2 = 2 sleep triggers (at call 2 and 4)
        $agent->call($messages);
        $agent->call($messages);
        $agent->call($messages);
        $agent->call($messages);
    }

    public function testCallReturnsResultWhenSleepPhaseFails()
    {
        $messages = new MessageBag(Message::ofUser('Hi'));
        $expectedResult = new TextResult('Hello!');

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->method('call')->willReturn($expectedResult);

        $sleepingAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent->method('call')
            ->willThrowException(new \RuntimeException('LLM API error'));

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')], 1);

        $result = $agent->call($messages);

        $this->assertSame($expectedResult, $result);
    }

    public function testCallLogsWarningWhenSleepPhaseFails()
    {
        $messages = new MessageBag(Message::ofUser('Hi'));

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->method('call')->willReturn(new TextResult('Hello!'));

        $sleepingAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent->method('call')
            ->willThrowException(new \RuntimeException('LLM API error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Sleep-time processing failed'),
                $this->callback(static fn (array $context): bool => 'LLM API error' === $context['exception']),
            );

        $agent = new SleepTimeAgent(
            $primaryAgent,
            $sleepingAgent,
            [new MemoryBlock('summary')],
            1,
            'sleep-time-agent',
            $logger,
        );

        $agent->call($messages);
    }

    public function testSleepPhaseReceivesConversationContext()
    {
        $messages = new MessageBag(
            Message::ofUser('My name is John'),
        );
        $primaryResult = new TextResult('Nice to meet you, John!');

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->method('call')->willReturn($primaryResult);

        $sleepingAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent->expects($this->once())
            ->method('call')
            ->with($this->callback(static function (MessageBag $sleepMessages): bool {
                $userMessage = $sleepMessages->getUserMessage();
                if (null === $userMessage) {
                    return false;
                }

                $text = $userMessage->asText();

                return null !== $text && str_contains($text, 'My name is John');
            }));

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')], 1);

        $agent->call($messages);
    }

    public function testSleepPhaseSystemPromptIncludesMemoryBlockLabels()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $primaryResult = new TextResult('Hi!');

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->method('call')->willReturn($primaryResult);

        $sleepingAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent->expects($this->once())
            ->method('call')
            ->with($this->callback(static function (MessageBag $sleepMessages): bool {
                $systemMessage = $sleepMessages->getSystemMessage();
                if (null === $systemMessage) {
                    return false;
                }

                $content = $systemMessage->getContent();
                if (!\is_string($content)) {
                    return false;
                }

                return str_contains($content, 'summary')
                    && str_contains($content, 'preferences')
                    && str_contains($content, 'rethink_memory');
            }));

        $agent = new SleepTimeAgent(
            $primaryAgent,
            $sleepingAgent,
            [new MemoryBlock('summary'), new MemoryBlock('preferences')],
            1,
        );

        $agent->call($messages);
    }

    public function testSleepPhaseWithFrequencyOfOne()
    {
        $messages = new MessageBag(Message::ofUser('Hi'));
        $primaryResult = new TextResult('Hello!');

        $primaryAgent = $this->createMock(AgentInterface::class);
        $primaryAgent->method('call')->willReturn($primaryResult);

        $sleepingAgent = $this->createMock(AgentInterface::class);
        $sleepingAgent->expects($this->exactly(3))->method('call');

        $agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, [new MemoryBlock('summary')], 1);

        $agent->call($messages);
        $agent->call($messages);
        $agent->call($messages);
    }
}

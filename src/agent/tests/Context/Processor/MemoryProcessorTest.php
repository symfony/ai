<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Context\Processor;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\Processor\MemoryProcessor;
use Symfony\AI\Agent\Memory\Memory;
use Symfony\AI\Agent\Memory\MemoryProviderInterface;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class MemoryProcessorTest extends TestCase
{
    public function testItIsDoingNothingOnInactiveMemory()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->never())->method($this->anything());

        $memoryProcessor = new MemoryProcessor([$memoryProvider]);
        $memoryProcessor->process(
            $input = new AgentRequest('gpt-4', new MessageBag(), ['use_memory' => false], new Context()),
            new AgentContext(new MockAgent()),
        );

        $this->assertArrayNotHasKey('use_memory', $input->getOptions());
    }

    public function testItIsDoingNothingWhenThereAreNoProviders()
    {
        $memoryProcessor = new MemoryProcessor([]);
        $memoryProcessor->process(
            $input = new AgentRequest('gpt-4', new MessageBag(), ['use_memory' => true], new Context()),
            new AgentContext(new MockAgent()),
        );

        $this->assertArrayNotHasKey('use_memory', $input->getOptions());
    }

    public function testItIsAddingMemoryToSystemPrompt()
    {
        $firstMemoryProvider = $this->createMock(MemoryProviderInterface::class);
        $firstMemoryProvider->expects($this->once())
            ->method('load')
            ->willReturn([new Memory('First memory content')]);

        $secondMemoryProvider = $this->createMock(MemoryProviderInterface::class);
        $secondMemoryProvider->expects($this->once())
            ->method('load')
            ->willReturn([]);

        $memoryProcessor = new MemoryProcessor([
            $firstMemoryProvider,
            $secondMemoryProvider,
        ]);

        $memoryProcessor->process(
            $input = new AgentRequest('gpt-4', new MessageBag(Message::forSystem('You are a helpful and kind assistant.')), [], new Context()),
            new AgentContext(new MockAgent()),
        );

        $this->assertArrayNotHasKey('use_memory', $input->getOptions());
        $this->assertSame(
            <<<MARKDOWN
                # Conversation Memory
                This is the memory I have found for this conversation. The memory has more weight to answer user input,
                so try to answer utilizing the memory as much as possible. Your answer must be changed to fit the given
                memory. If the memory is irrelevant, ignore it. Do not reply to the this section of the prompt and do not
                reference it as this is just for your reference.

                First memory content

                # System Prompt

                You are a helpful and kind assistant.
                MARKDOWN,
            $input->getMessageBag()->getSystemMessage()->getContent(),
        );
    }

    public function testItIsAddingMemoryToSystemPromptEvenItIsEmpty()
    {
        $firstMemoryProvider = $this->createMock(MemoryProviderInterface::class);
        $firstMemoryProvider->expects($this->once())
            ->method('load')
            ->willReturn([new Memory('First memory content')]);

        $memoryProcessor = new MemoryProcessor([$firstMemoryProvider]);

        $memoryProcessor->process($input = new AgentRequest('gpt-4', new MessageBag(), [], new Context()), new AgentContext(new MockAgent()));

        $this->assertArrayNotHasKey('use_memory', $input->getOptions());
        $this->assertSame(
            <<<MARKDOWN
                # Conversation Memory
                This is the memory I have found for this conversation. The memory has more weight to answer user input,
                so try to answer utilizing the memory as much as possible. Your answer must be changed to fit the given
                memory. If the memory is irrelevant, ignore it. Do not reply to the this section of the prompt and do not
                reference it as this is just for your reference.

                First memory content
                MARKDOWN,
            $input->getMessageBag()->getSystemMessage()->getContent(),
        );
    }

    public function testItIsAddingMultipleMemoryFromSingleProviderToSystemPrompt()
    {
        $firstMemoryProvider = $this->createMock(MemoryProviderInterface::class);
        $firstMemoryProvider->expects($this->once())
            ->method('load')
            ->willReturn([new Memory('First memory content'), new Memory('Second memory content')]);

        $memoryProcessor = new MemoryProcessor([$firstMemoryProvider]);

        $memoryProcessor->process($input = new AgentRequest('gpt-4', new MessageBag(), [], new Context()), new AgentContext(new MockAgent()));

        $this->assertArrayNotHasKey('use_memory', $input->getOptions());
        $this->assertSame(
            <<<MARKDOWN
                # Conversation Memory
                This is the memory I have found for this conversation. The memory has more weight to answer user input,
                so try to answer utilizing the memory as much as possible. Your answer must be changed to fit the given
                memory. If the memory is irrelevant, ignore it. Do not reply to the this section of the prompt and do not
                reference it as this is just for your reference.

                First memory content
                Second memory content
                MARKDOWN,
            $input->getMessageBag()->getSystemMessage()->getContent(),
        );
    }

    public function testItIsNotAddingAnythingIfMemoryWasEmpty()
    {
        $firstMemoryProvider = $this->createMock(MemoryProviderInterface::class);
        $firstMemoryProvider->expects($this->once())
            ->method('load')
            ->willReturn([]);

        $memoryProcessor = new MemoryProcessor([$firstMemoryProvider]);

        $memoryProcessor->process($input = new AgentRequest('gpt-4', new MessageBag(), [], new Context()), new AgentContext(new MockAgent()));

        $this->assertArrayNotHasKey('use_memory', $input->getOptions());
        $this->assertNull($input->getMessageBag()->getSystemMessage()?->getContent());
    }

    public function testItMutatesTheCallerMessageBagInPlace()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->once())
            ->method('load')
            ->willReturn([new Memory('Some memory content')]);

        $memoryProcessor = new MemoryProcessor([$memoryProvider]);

        $bag = new MessageBag(Message::forSystem('You are a helpful assistant.'));
        $memoryProcessor->process($input = new AgentRequest('gpt-4', $bag, [], new Context()), new AgentContext(new MockAgent()));

        // Caller's bag must reflect the combined system message so downstream
        // processors can append messages visible to the caller's reference.
        // See #1726.
        $this->assertSame($bag, $input->getMessageBag());
        $this->assertCount(1, $bag);
        $this->assertStringContainsString('Some memory content', $bag->getSystemMessage()->getContent());
    }

    public function testItDoesNotCompoundTheMemoryPromptOnRepeatedCallsWithTheSameBag()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->exactly(2))
            ->method('load')
            ->willReturn([new Memory('User likes PHP')]);

        $memoryProcessor = new MemoryProcessor([$memoryProvider]);

        // Since the processor mutates the caller's bag in place, the combined
        // system message survives the agent call. Chat::submit() persists that
        // bag and reuses it on the next turn, so the processor runs again on
        // its own output and must not wrap the memory prompt a second time.
        $bag = new MessageBag(Message::forSystem('You are a helpful assistant.'));

        $memoryProcessor->process(new AgentRequest('gpt-4', $bag, [], new Context()), new AgentContext(new MockAgent()));
        $firstTurnSystemMessage = $bag->getSystemMessage()->getContent();

        $memoryProcessor->process(new AgentRequest('gpt-4', $bag, [], new Context()), new AgentContext(new MockAgent()));
        $secondTurnSystemMessage = $bag->getSystemMessage()->getContent();

        $this->assertSame(1, substr_count($secondTurnSystemMessage, '# Conversation Memory'));
        $this->assertSame($firstTurnSystemMessage, $secondTurnSystemMessage);
    }

    public function testItKeepsTheOriginalSystemPromptInTheCombinedMessageMetadata()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->once())
            ->method('load')
            ->willReturn([new Memory('Some memory content')]);

        $memoryProcessor = new MemoryProcessor([$memoryProvider]);

        $bag = new MessageBag(Message::forSystem('You are a helpful assistant.'));
        $memoryProcessor->process(new AgentRequest('gpt-4', $bag, [], new Context()), new AgentContext(new MockAgent()));

        // The original prompt must travel as metadata on the combined message:
        // Chat message stores persist metadata, so idempotence survives a
        // serialization round-trip through the store.
        $this->assertSame(
            'You are a helpful assistant.',
            $bag->getSystemMessage()->getMetadata()->get('memory_original_system_prompt'),
        );
    }

    public function testItIsIdempotentWhenThereWasNoOriginalSystemPrompt()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->exactly(2))
            ->method('load')
            ->willReturn([new Memory('Some memory content')]);

        $memoryProcessor = new MemoryProcessor([$memoryProvider]);

        $bag = new MessageBag(Message::ofUser('Hi'));

        $memoryProcessor->process(new AgentRequest('gpt-4', $bag, [], new Context()), new AgentContext(new MockAgent()));
        $firstTurnSystemMessage = $bag->getSystemMessage()->getContent();

        $memoryProcessor->process(new AgentRequest('gpt-4', $bag, [], new Context()), new AgentContext(new MockAgent()));
        $secondTurnSystemMessage = $bag->getSystemMessage()->getContent();

        $this->assertStringNotContainsString('# System Prompt', $secondTurnSystemMessage);
        $this->assertSame($firstTurnSystemMessage, $secondTurnSystemMessage);
    }

    public function testItRefreshesTheMemoryOnSubsequentCallsWithTheSameBag()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->exactly(2))
            ->method('load')
            ->willReturnOnConsecutiveCalls(
                [new Memory('First turn memory')],
                [new Memory('Second turn memory')],
            );

        $memoryProcessor = new MemoryProcessor([$memoryProvider]);

        $bag = new MessageBag(Message::forSystem('You are a helpful assistant.'));

        $memoryProcessor->process(new AgentRequest('gpt-4', $bag, [], new Context()), new AgentContext(new MockAgent()));
        $memoryProcessor->process(new AgentRequest('gpt-4', $bag, [], new Context()), new AgentContext(new MockAgent()));

        $systemMessage = $bag->getSystemMessage()->getContent();

        // Memory must be re-derived per call, while the original prompt is kept.
        $this->assertStringContainsString('Second turn memory', $systemMessage);
        $this->assertStringNotContainsString('First turn memory', $systemMessage);
        $this->assertStringContainsString('You are a helpful assistant.', $systemMessage);
    }
}

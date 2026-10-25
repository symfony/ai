<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Memory;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Memory\Memory;
use Symfony\AI\Agent\Memory\MemoryInputProcessor;
use Symfony\AI\Agent\Memory\MemoryProviderInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class MemoryInputProcessorTest extends TestCase
{
    public function testItIsDoingNothingOnInactiveMemory()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->never())->method($this->anything());

        $memoryInputProcessor = new MemoryInputProcessor([$memoryProvider]);
        $memoryInputProcessor->processInput(
            $input = new Input('gpt-4', new MessageBag(), ['use_memory' => false]),
        );

        $this->assertArrayNotHasKey('use_memory', $input->getOptions());
    }

    public function testItIsDoingNothingWhenThereAreNoProviders()
    {
        $memoryInputProcessor = new MemoryInputProcessor([]);
        $memoryInputProcessor->processInput(
            $input = new Input('gpt-4', new MessageBag(), ['use_memory' => true]),
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

        $memoryInputProcessor = new MemoryInputProcessor([
            $firstMemoryProvider,
            $secondMemoryProvider,
        ]);

        $memoryInputProcessor->processInput($input = new Input(
            'gpt-4',
            new MessageBag(Message::forSystem('You are a helpful and kind assistant.')),
            [],
        ));

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

        $memoryInputProcessor = new MemoryInputProcessor([$firstMemoryProvider]);

        $memoryInputProcessor->processInput($input = new Input('gpt-4', new MessageBag(), []));

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

        $memoryInputProcessor = new MemoryInputProcessor([$firstMemoryProvider]);

        $memoryInputProcessor->processInput($input = new Input('gpt-4', new MessageBag(), []));

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

        $memoryInputProcessor = new MemoryInputProcessor([$firstMemoryProvider]);

        $memoryInputProcessor->processInput($input = new Input('gpt-4', new MessageBag(), []));

        $this->assertArrayNotHasKey('use_memory', $input->getOptions());
        $this->assertNull($input->getMessageBag()->getSystemMessage()?->getContent());
    }

    public function testItLeavesTheCallerMessageBagUntouched()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->once())
            ->method('load')
            ->willReturn([new Memory('Some memory content')]);

        $memoryInputProcessor = new MemoryInputProcessor([$memoryProvider]);

        $bag = new MessageBag(Message::forSystem('You are a helpful assistant.'));
        $memoryInputProcessor->processInput($input = new Input('gpt-4', $bag, []));

        $this->assertSame('You are a helpful assistant.', $bag->getSystemMessage()->getContent());
        $this->assertCount(1, $input->getMessageBag());
        $this->assertStringContainsString('Some memory content', $input->getMessageBag()->getSystemMessage()->getContent());
    }

    public function testItDoesNotCompoundTheMemoryPromptOnRepeatedCallsWithTheSameBag()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->exactly(2))
            ->method('load')
            ->willReturn([new Memory('User likes PHP')]);

        $memoryInputProcessor = new MemoryInputProcessor([$memoryProvider]);

        // a persisted bag can carry the combined system message into the next turn
        $firstInput = new Input('gpt-4', new MessageBag(Message::forSystem('You are a helpful assistant.')), []);
        $memoryInputProcessor->processInput($firstInput);
        $firstTurnSystemMessage = $firstInput->getMessageBag()->getSystemMessage()->getContent();

        $secondInput = new Input('gpt-4', $firstInput->getMessageBag(), []);
        $memoryInputProcessor->processInput($secondInput);
        $secondTurnSystemMessage = $secondInput->getMessageBag()->getSystemMessage()->getContent();

        $this->assertSame(1, substr_count($secondTurnSystemMessage, '# Conversation Memory'));
        $this->assertSame($firstTurnSystemMessage, $secondTurnSystemMessage);
    }

    public function testItKeepsTheOriginalSystemPromptInTheCombinedMessageMetadata()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->once())
            ->method('load')
            ->willReturn([new Memory('Some memory content')]);

        $memoryInputProcessor = new MemoryInputProcessor([$memoryProvider]);

        $input = new Input('gpt-4', new MessageBag(Message::forSystem('You are a helpful assistant.')), []);
        $memoryInputProcessor->processInput($input);

        // persisted as message metadata, so idempotence survives a round-trip through a message store
        $this->assertSame(
            'You are a helpful assistant.',
            $input->getMessageBag()->getSystemMessage()->getMetadata()->get('memory_original_system_prompt'),
        );
    }

    public function testItIsIdempotentWhenThereWasNoOriginalSystemPrompt()
    {
        $memoryProvider = $this->createMock(MemoryProviderInterface::class);
        $memoryProvider->expects($this->exactly(2))
            ->method('load')
            ->willReturn([new Memory('Some memory content')]);

        $memoryInputProcessor = new MemoryInputProcessor([$memoryProvider]);

        $firstInput = new Input('gpt-4', new MessageBag(Message::ofUser('Hi')), []);
        $memoryInputProcessor->processInput($firstInput);
        $firstTurnSystemMessage = $firstInput->getMessageBag()->getSystemMessage()->getContent();

        $secondInput = new Input('gpt-4', $firstInput->getMessageBag(), []);
        $memoryInputProcessor->processInput($secondInput);
        $secondTurnSystemMessage = $secondInput->getMessageBag()->getSystemMessage()->getContent();

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

        $memoryInputProcessor = new MemoryInputProcessor([$memoryProvider]);

        $firstInput = new Input('gpt-4', new MessageBag(Message::forSystem('You are a helpful assistant.')), []);
        $memoryInputProcessor->processInput($firstInput);
        $secondInput = new Input('gpt-4', $firstInput->getMessageBag(), []);
        $memoryInputProcessor->processInput($secondInput);

        $systemMessage = $secondInput->getMessageBag()->getSystemMessage()->getContent();

        // Memory must be re-derived per call, while the original prompt is kept.
        $this->assertStringContainsString('Second turn memory', $systemMessage);
        $this->assertStringNotContainsString('First turn memory', $systemMessage);
        $this->assertStringContainsString('You are a helpful assistant.', $systemMessage);
    }
}

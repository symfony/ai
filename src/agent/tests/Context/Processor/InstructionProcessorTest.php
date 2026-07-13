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
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Context\Processor\InstructionProcessor;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\Tests\Fixtures\SystemPromptService;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class InstructionProcessorTest extends TestCase
{
    public function testItAddsTheSystemMessageWhenNoneExists()
    {
        $messages = new MessageBag(Message::ofUser('This is a user message'));

        $this->process(new InstructionProcessor(), $messages, new Instruction('This is a system prompt'));

        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages->getMessages()[0]);
        $this->assertInstanceOf(UserMessage::class, $messages->getMessages()[1]);
        $this->assertSame('This is a system prompt', $messages->getMessages()[0]->getContent());
    }

    public function testItMutatesTheCallersMessageBagInPlace()
    {
        $messages = new MessageBag(Message::ofUser('This is a user message'));

        $request = $this->process(new InstructionProcessor(), $messages, new Instruction('This is a system prompt'));

        $this->assertSame($messages, $request->getMessageBag());
        $this->assertCount(2, $messages);
    }

    public function testItDoesNotAddTheSystemMessageWhenOneExists()
    {
        $messages = new MessageBag(
            Message::forSystem('This is a system message'),
            Message::ofUser('This is a user message'),
        );

        $this->process(new InstructionProcessor(), $messages, new Instruction('This is a system prompt'));

        $this->assertCount(2, $messages);
        $this->assertSame('This is a system message', $messages->getMessages()[0]->getContent());
    }

    public function testItDoesNothingWithoutAnInstructionInTheContext()
    {
        $messages = new MessageBag(Message::ofUser('This is a user message'));

        $this->process(new InstructionProcessor(), $messages);

        $this->assertCount(1, $messages);
    }

    public function testItCombinesSeveralInstructions()
    {
        $messages = new MessageBag(Message::ofUser('This is a user message'));

        $this->process(
            new InstructionProcessor(),
            $messages,
            new Instruction('Be brief.'),
            new Instruction('Be kind.'),
        );

        $this->assertSame("Be brief.\n\nBe kind.", $messages->getMessages()[0]->getContent());
    }

    public function testItDoesNotIncludeToolsIfToolboxIsEmpty()
    {
        $messages = new MessageBag(Message::ofUser('This is a user message'));
        $processor = new InstructionProcessor(toolbox: $this->createToolbox());

        $this->process($processor, $messages, new Instruction('This is a system prompt'));

        $this->assertCount(2, $messages);
        $this->assertSame('This is a system prompt', $messages->getMessages()[0]->getContent());
    }

    public function testItIncludesToolDefinitions()
    {
        $messages = new MessageBag(Message::ofUser('This is a user message'));
        $processor = new InstructionProcessor(
            $this->getTranslator(),
            $this->createToolbox(
                new Tool(new ExecutionReference(ToolNoParams::class), 'tool_no_params', 'A tool without parameters', null),
                new Tool(
                    new ExecutionReference(ToolRequiredParams::class, 'bar'),
                    'tool_required_params',
                    <<<DESCRIPTION
                        A tool with required parameters
                        or not
                        DESCRIPTION,
                    null
                ),
            ),
        );

        $this->process($processor, $messages, new Instruction(new TranslatableMessage('This is a')));

        $this->assertCount(2, $messages);
        $this->assertSame(<<<PROMPT
            This is a cool translated system prompt

            # Tools

            The following tools are available to assist you in completing the user's request:

            ## tool_no_params
            A tool without parameters

            ## tool_required_params
            A tool with required parameters
            or not
            PROMPT, $messages->getMessages()[0]->getContent());
    }

    public function testItRendersAStringableInstruction()
    {
        $messages = new MessageBag(Message::ofUser('This is a user message'));
        $processor = new InstructionProcessor(
            toolbox: $this->createToolbox(
                new Tool(new ExecutionReference(ToolNoParams::class), 'tool_no_params', 'A tool without parameters', null),
            ),
        );

        $this->process($processor, $messages, new Instruction(new SystemPromptService()));

        $this->assertSame(<<<PROMPT
            My dynamic system prompt.

            # Tools

            The following tools are available to assist you in completing the user's request:

            ## tool_no_params
            A tool without parameters
            PROMPT, $messages->getMessages()[0]->getContent());
    }

    public function testItRendersATranslatableInstruction()
    {
        $messages = new MessageBag(Message::ofUser('This is a user message'));

        $this->process(
            new InstructionProcessor($this->getTranslator()),
            $messages,
            new Instruction(new TranslatableMessage('This is a')),
        );

        $this->assertSame('This is a cool translated system prompt', $messages->getMessages()[0]->getContent());
    }

    public function testItRendersATranslatableInstructionWithADomain()
    {
        $messages = new MessageBag();

        $this->process(
            new InstructionProcessor($this->getTranslator()),
            $messages,
            new Instruction(new TranslatableMessage('This is a', domain: 'prompts')),
        );

        $this->assertCount(1, $messages);
        $this->assertSame('This is a cool translated system prompt with a translation domain', $messages->getMessages()[0]->getContent());
    }

    public function testItThrowsForATranslatableInstructionWithoutATranslator()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Translatable instruction is not supported when no translator is provided.');

        $this->process(
            new InstructionProcessor(),
            new MessageBag(),
            new Instruction(new TranslatableMessage('This is a')),
        );
    }

    public function testItRendersAFileInstruction()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'prompt_');
        file_put_contents($tempFile, 'This is a system prompt from a file');

        try {
            $messages = new MessageBag(Message::ofUser('This is a user message'));

            $this->process(new InstructionProcessor(), $messages, new Instruction(File::fromFile($tempFile)));

            $this->assertCount(2, $messages);
            $this->assertSame('This is a system prompt from a file', $messages->getMessages()[0]->getContent());
        } finally {
            unlink($tempFile);
        }
    }

    public function testItRendersAMultilineFileInstruction()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'prompt_');
        file_put_contents($tempFile, "Line 1\nLine 2\nLine 3");

        try {
            $messages = new MessageBag(Message::ofUser('This is a user message'));

            $this->process(new InstructionProcessor(), $messages, new Instruction(File::fromFile($tempFile)));

            $this->assertCount(2, $messages);
            $this->assertSame("Line 1\nLine 2\nLine 3", $messages->getMessages()[0]->getContent());
        } finally {
            unlink($tempFile);
        }
    }

    private function process(InstructionProcessor $processor, MessageBag $messages, object ...$items): AgentRequest
    {
        $request = new AgentRequest('gpt-4o', $messages, [], new Context(...$items));
        $processor->process($request, new AgentContext(new MockAgent()));

        return $request;
    }

    private function createToolbox(Tool ...$tools): ToolboxInterface
    {
        return new class($tools) implements ToolboxInterface {
            /**
             * @param Tool[] $tools
             */
            public function __construct(private readonly array $tools)
            {
            }

            public function getTools(): array
            {
                return $this->tools;
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                return new ToolResult($toolCall, null);
            }
        };
    }

    private function getTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            /**
             * @param array<mixed> $parameters
             */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $translated = \sprintf('%s cool translated system prompt', $id);

                return $domain ? $translated.' with a translation domain' : $translated;
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }
}

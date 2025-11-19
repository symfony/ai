<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\InputProcessor;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Fixtures\Tool\ToolRequiredParams;
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

final class SystemPromptInputProcessorTest extends TestCase
{
    public function testProcessInputAddsSystemMessageWhenNoneExists()
    {
        $processor = new SystemPromptInputProcessor('This is a system prompt');

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('This is a user message')));
        $processor->processInput($input);

        $messages = $input->getMessageBag()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame('This is a system prompt', $messages[0]->getContent());
    }

    public function testProcessInputDoesNotAddSystemMessageWhenOneExists()
    {
        $processor = new SystemPromptInputProcessor('This is a system prompt');

        $messages = new MessageBag(
            Message::forSystem('This is already a system prompt'),
            Message::ofUser('This is a user message'),
        );
        $input = new Input('gpt-4o', $messages);
        $processor->processInput($input);

        $messages = $input->getMessageBag()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame('This is already a system prompt', $messages[0]->getContent());
    }

    public function testDoesNotIncludeToolsIfToolboxIsEmpty()
    {
        $processor = new SystemPromptInputProcessor(
            'This is a system prompt',
            new class implements ToolboxInterface {
                public function getTools(): array
                {
                    return [];
                }

                public function execute(ToolCall $toolCall): ToolResult
                {
                    return new ToolResult($toolCall, null);
                }
            },
        );

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('This is a user message')));
        $processor->processInput($input);

        $messages = $input->getMessageBag()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame('This is a system prompt', $messages[0]->getContent());
    }

    public function testIncludeToolDefinitions()
    {
        $processor = new SystemPromptInputProcessor(
            new TranslatableMessage('This is a'),
            new class implements ToolboxInterface {
                public function getTools(): array
                {
                    return [
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
                    ];
                }

                public function execute(ToolCall $toolCall): ToolResult
                {
                    return new ToolResult($toolCall, null);
                }
            },
            $this->getTranslator(),
        );

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('This is a user message')));
        $processor->processInput($input);

        $messages = $input->getMessageBag()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame(<<<PROMPT
            This is a cool translated system prompt

            # Tools

            The following tools are available to assist you in completing the user's request:

            ## tool_no_params
            A tool without parameters

            ## tool_required_params
            A tool with required parameters
            or not
            PROMPT, $messages[0]->getContent());
    }

    public function testWithStringableSystemPrompt()
    {
        $processor = new SystemPromptInputProcessor(
            new SystemPromptService(),
            new class implements ToolboxInterface {
                public function getTools(): array
                {
                    return [
                        new Tool(new ExecutionReference(ToolNoParams::class), 'tool_no_params', 'A tool without parameters', null),
                    ];
                }

                public function execute(ToolCall $toolCall): ToolResult
                {
                    return new ToolResult($toolCall, null);
                }
            },
        );

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('This is a user message')));
        $processor->processInput($input);

        $messages = $input->getMessageBag()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame(<<<PROMPT
            My dynamic system prompt.

            # Tools

            The following tools are available to assist you in completing the user's request:

            ## tool_no_params
            A tool without parameters
            PROMPT, $messages[0]->getContent());
    }

    public function testWithTranslatedSystemPrompt()
    {
        $processor = new SystemPromptInputProcessor(new TranslatableMessage('This is a'), null, $this->getTranslator());

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('This is a user message')), []);
        $processor->processInput($input);

        $messages = $input->getMessageBag()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame('This is a cool translated system prompt', $messages[0]->getContent());
    }

    public function testWithTranslationDomainSystemPrompt()
    {
        $processor = new SystemPromptInputProcessor(
            new TranslatableMessage('This is a', domain: 'prompts'),
            null,
            $this->getTranslator(),
        );

        $input = new Input('gpt-4o', new MessageBag(), []);
        $processor->processInput($input);

        $messages = $input->getMessageBag()->getMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertSame('This is a cool translated system prompt with a translation domain', $messages[0]->getContent());
    }

    public function testWithMissingTranslator()
    {
        $this->expectExceptionMessage('Translatable system prompt is not supported when no translator is provided.');

        new SystemPromptInputProcessor(
            new TranslatableMessage('This is a'),
            null,
            null,
        );
    }

    public function testProcessInputWithFile()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'prompt_');
        file_put_contents($tempFile, 'This is a system prompt from a file');

        try {
            $file = File::fromFile($tempFile);
            $processor = new SystemPromptInputProcessor($file);

            $input = new Input('gpt-4o', new MessageBag(Message::ofUser('This is a user message')));
            $processor->processInput($input);

            $messages = $input->getMessageBag()->getMessages();
            $this->assertCount(2, $messages);
            $this->assertInstanceOf(SystemMessage::class, $messages[0]);
            $this->assertInstanceOf(UserMessage::class, $messages[1]);
            $this->assertSame('This is a system prompt from a file', $messages[0]->getContent());
        } finally {
            unlink($tempFile);
        }
    }

    public function testProcessInputWithMultilineFile()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'prompt_');
        file_put_contents($tempFile, "Line 1\nLine 2\nLine 3");

        try {
            $file = File::fromFile($tempFile);
            $processor = new SystemPromptInputProcessor($file);

            $input = new Input('gpt-4o', new MessageBag(Message::ofUser('This is a user message')));
            $processor->processInput($input);

            $messages = $input->getMessageBag()->getMessages();
            $this->assertCount(2, $messages);
            $this->assertInstanceOf(SystemMessage::class, $messages[0]);
            $this->assertSame("Line 1\nLine 2\nLine 3", $messages[0]->getContent());
        } finally {
            unlink($tempFile);
        }
    }

    private function getTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
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

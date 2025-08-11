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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

#[CoversClass(SystemPromptInputProcessor::class)]
#[UsesClass(Gpt::class)]
#[UsesClass(Message::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(Input::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(Text::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ExecutionReference::class)]
#[Small]
final class SystemPromptInputProcessorTest extends TestCase
{
    public function testProcessInputAddsSystemMessageWhenNoneExists()
    {
        $processor = new SystemPromptInputProcessor('This is a system prompt');

        $input = new Input(Gpt::create(), new MessageBag(Message::ofUser('This is a user message')), []);
        $processor->processInput($input);

        $messages = $input->messages->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame('This is a system prompt', $messages[0]->content);
    }

    public function testProcessInputDoesNotAddSystemMessageWhenOneExists()
    {
        $processor = new SystemPromptInputProcessor('This is a system prompt');

        $messages = new MessageBag(
            Message::forSystem('This is already a system prompt'),
            Message::ofUser('This is a user message'),
        );
        $input = new Input(Gpt::create(), $messages, []);
        $processor->processInput($input);

        $messages = $input->messages->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame('This is already a system prompt', $messages[0]->content);
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

                public function execute(ToolCall $toolCall): mixed
                {
                    return null;
                }
            }
        );

        $input = new Input(Gpt::create(), new MessageBag(Message::ofUser('This is a user message')), []);
        $processor->processInput($input);

        $messages = $input->messages->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame('This is a system prompt', $messages[0]->content);
    }

    public function testIncludeToolDefinitions()
    {
        $processor = new SystemPromptInputProcessor(
            'This is a system prompt',
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

                public function execute(ToolCall $toolCall): mixed
                {
                    return null;
                }
            }
        );

        $input = new Input(Gpt::create(), new MessageBag(Message::ofUser('This is a user message')), []);
        $processor->processInput($input);

        $messages = $input->messages->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame(<<<PROMPT
            This is a system prompt

            # Available tools

            ## tool_no_params
            A tool without parameters

            ## tool_required_params
            A tool with required parameters
            or not
            PROMPT, $messages[0]->content);
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

                public function execute(ToolCall $toolCall): mixed
                {
                    return null;
                }
            }
        );

        $input = new Input(Gpt::create(), new MessageBag(Message::ofUser('This is a user message')), []);
        $processor->processInput($input);

        $messages = $input->messages->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame(<<<PROMPT
            My dynamic system prompt.

            # Available tools

            ## tool_no_params
            A tool without parameters
            PROMPT, $messages[0]->content);
    }
}

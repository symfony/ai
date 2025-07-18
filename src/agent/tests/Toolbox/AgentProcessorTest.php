<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

#[CoversClass(AgentProcessor::class)]
#[UsesClass(Input::class)]
#[UsesClass(Output::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCallResult::class)]
#[UsesClass(ExecutionReference::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(MissingModelSupportException::class)]
#[UsesClass(Model::class)]
class AgentProcessorTest extends TestCase
{
    #[Test]
    public function processInputWithoutRegisteredToolsWillResultInNoOptionChange(): void
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);

        $model = new Model('gpt-4', [Capability::TOOL_CALLING]);
        $processor = new AgentProcessor($toolbox);
        $input = new Input($model, new MessageBag(), []);

        $processor->processInput($input);

        self::assertSame([], $input->getOptions());
    }

    #[Test]
    public function processInputWithRegisteredToolsWillResultInOptionChange(): void
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $model = new Model('gpt-4', [Capability::TOOL_CALLING]);
        $processor = new AgentProcessor($toolbox);
        $input = new Input($model, new MessageBag(), []);

        $processor->processInput($input);

        self::assertSame(['tools' => [$tool1, $tool2]], $input->getOptions());
    }

    #[Test]
    public function processInputWithRegisteredToolsButToolOverride(): void
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $model = new Model('gpt-4', [Capability::TOOL_CALLING]);
        $processor = new AgentProcessor($toolbox);
        $input = new Input($model, new MessageBag(), ['tools' => ['tool2']]);

        $processor->processInput($input);

        self::assertSame(['tools' => [$tool2]], $input->getOptions());
    }

    #[Test]
    public function processInputWithUnsupportedToolCallingWillThrowException(): void
    {
        self::expectException(MissingModelSupportException::class);

        $model = new Model('gpt-3');
        $processor = new AgentProcessor($this->createStub(ToolboxInterface::class));
        $input = new Input($model, new MessageBag(), []);

        $processor->processInput($input);
    }

    #[Test]
    public function processOutputWithToolCallResponseKeepingMessages(): void
    {
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->expects($this->once())->method('execute')->willReturn('Test response');

        $model = new Model('gpt-4', [Capability::TOOL_CALLING]);

        $messageBag = new MessageBag();

        $result = new ToolCallResult(new ToolCall('id1', 'tool1', ['arg1' => 'value1']));

        $agent = $this->createStub(AgentInterface::class);

        $processor = new AgentProcessor($toolbox, keepToolMessages: true);
        $processor->setAgent($agent);

        $output = new Output($model, $result, $messageBag, []);

        $processor->processOutput($output);

        self::assertCount(2, $messageBag);
        self::assertInstanceOf(AssistantMessage::class, $messageBag->getMessages()[0]);
        self::assertInstanceOf(ToolCallMessage::class, $messageBag->getMessages()[1]);
    }

    #[Test]
    public function processOutputWithToolCallResponseForgettingMessages(): void
    {
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->expects($this->once())->method('execute')->willReturn('Test response');

        $model = new Model('gpt-4', [Capability::TOOL_CALLING]);

        $messageBag = new MessageBag();

        $result = new ToolCallResult(new ToolCall('id1', 'tool1', ['arg1' => 'value1']));

        $agent = $this->createStub(AgentInterface::class);

        $processor = new AgentProcessor($toolbox, keepToolMessages: false);
        $processor->setAgent($agent);

        $output = new Output($model, $result, $messageBag, []);

        $processor->processOutput($output);

        self::assertCount(0, $messageBag);
    }
}

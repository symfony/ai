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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\StreamResult as GenericStreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\PlainConverter;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

class AgentProcessorTest extends TestCase
{
    public function testProcessInputWithoutRegisteredToolsWillResultInNoOptionChange()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);

        $processor = new AgentProcessor($toolbox);
        $input = new Input('gpt-4', new MessageBag());

        $processor->processInput($input);

        $this->assertSame([], $input->getOptions());
    }

    public function testProcessInputWithRegisteredToolsWillResultInOptionChange()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $processor = new AgentProcessor($toolbox);
        $input = new Input('gpt-4', new MessageBag());

        $processor->processInput($input);

        $this->assertSame(['tools' => [$tool1, $tool2]], $input->getOptions());
    }

    public function testProcessInputWithRegisteredToolsButToolOverride()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $processor = new AgentProcessor($toolbox);
        $input = new Input('gpt-4', new MessageBag(), ['tools' => ['tool2']]);

        $processor->processInput($input);

        $this->assertSame(['tools' => [$tool2]], $input->getOptions());
    }

    public function testProcessOutputWithToolCallResponseKeepingMessages()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $messageBag = new MessageBag();
        $result = new ToolCallResult($toolCall);

        $agent = $this->createStub(AgentInterface::class);

        $processor = new AgentProcessor($toolbox, keepToolMessages: true);
        $processor->setAgent($agent);

        $output = new Output('gpt-4', $result, $messageBag);

        $processor->processOutput($output);

        $this->assertCount(2, $messageBag);
        $this->assertInstanceOf(AssistantMessage::class, $messageBag->getMessages()[0]);
        $this->assertInstanceOf(ToolCallMessage::class, $messageBag->getMessages()[1]);
    }

    public function testProcessOutputWithToolCallResponseForgettingMessages()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $messageBag = new MessageBag();
        $result = new ToolCallResult($toolCall);

        $agent = $this->createStub(AgentInterface::class);

        $processor = new AgentProcessor($toolbox, keepToolMessages: false);
        $processor->setAgent($agent);

        $output = new Output('gpt-4', $result, $messageBag);

        $processor->processOutput($output);

        $this->assertCount(0, $messageBag);
    }

    public function testSourcesEndUpInResultMetadataWithSettingOn()
    {
        $toolCall = new ToolCall('call_1234', 'tool_sources', ['arg1' => 'value1']);
        $source1 = new Source('Relevant Article 1', 'http://example.com/article1', 'Content of article about the topic');
        $source2 = new Source('Relevant Article 2', 'http://example.com/article2', 'More content of article about the topic');
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Response based on the two articles.', [$source1, $source2]));

        $messageBag = new MessageBag();
        $result = new ToolCallResult($toolCall);

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturn(new TextResult('Final response based on the two articles.'));

        $processor = new AgentProcessor($toolbox, includeSources: true);
        $processor->setAgent($agent);

        $output = new Output('gpt-4', $result, $messageBag);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
        $this->assertTrue($metadata->has('sources'));
        $this->assertCount(2, $metadata->get('sources'));
        $this->assertSame([$source1, $source2], $metadata->get('sources'));
    }

    public function testSourcesDoNotEndUpInResultMetadataWithSettingOff()
    {
        $toolCall = new ToolCall('call_1234', 'tool_sources', ['arg1' => 'value1']);
        $source1 = new Source('Relevant Article 1', 'http://example.com/article1', 'Content of article about the topic');
        $source2 = new Source('Relevant Article 2', 'http://example.com/article2', 'More content of article about the topic');
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Response based on the two articles.', [$source1, $source2]));

        $messageBag = new MessageBag();
        $result = new ToolCallResult($toolCall);

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturn(new TextResult('Final response based on the two articles.'));

        $processor = new AgentProcessor($toolbox, includeSources: false);
        $processor->setAgent($agent);

        $output = new Output('gpt-4', $result, $messageBag);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
        $this->assertFalse($metadata->has('sources'));
    }

    public function testSourcesGetCollectedAcrossConsecutiveToolCalls()
    {
        $toolCall1 = new ToolCall('call_1234', 'tool_sources', ['arg1' => 'value1']);
        $source1 = new Source('Relevant Article 1', 'http://example.com/article1', 'Content of article about the topic');
        $toolCall2 = new ToolCall('call_5678', 'tool_sources', ['arg1' => 'value2']);
        $source2 = new Source('Relevant Article 2', 'http://example.com/article2', 'More content of article about the topic');

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                new ToolResult($toolCall1, 'Response based on the first article.', [$source1]),
                new ToolResult($toolCall2, 'Response based on the second article.', [$source2])
            );

        $messageBag = new MessageBag();
        $result = new ToolCallResult($toolCall1);

        $platform = $this->createMock(PlatformInterface::class);
        $platform
            ->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnOnConsecutiveCalls(
                new DeferredResult(new PlainConverter(new ToolCallResult($toolCall2)), new InMemoryRawResult()),
                new DeferredResult(new PlainConverter(new TextResult('Final response based on both articles.')), new InMemoryRawResult())
            );

        $processor = new AgentProcessor($toolbox, includeSources: true);
        $agent = new Agent($platform, 'foo-bar', [$processor], [$processor]);
        $processor->setAgent($agent);

        $output = new Output('gpt-4', $result, $messageBag);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
        $this->assertTrue($metadata->has('sources'));
        $this->assertCount(2, $metadata->get('sources'));
        $this->assertSame([$source1, $source2], $metadata->get('sources'));
    }

    public function testSourcesEndUpInResultMetadataWithStreaming()
    {
        $toolCall = new ToolCall('call_1234', 'tool_sources', ['arg1' => 'value1']);
        $source1 = new Source('Relevant Article 1', 'http://example.com/article1', 'Content of article about the topic');
        $source2 = new Source('Relevant Article 2', 'http://example.com/article2', 'More content of article about the topic');
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Response based on the two articles.', [$source1, $source2]));

        $messageBag = new MessageBag();

        // Create a generator that yields chunks and then a ToolCallResult
        $generator = (function () use ($toolCall) {
            yield 'chunk1';
            yield 'chunk2';
            yield new ToolCallResult($toolCall);
        })();

        $result = new GenericStreamResult($generator);

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturn(new TextResult('Final response based on the two articles.'));

        $processor = new AgentProcessor($toolbox, includeSources: true);
        $processor->setAgent($agent);

        $output = new Output('gpt-4', $result, $messageBag);

        $processor->processOutput($output);

        // Consume the stream
        $content = '';
        foreach ($output->getResult()->getContent() as $chunk) {
            $content .= $chunk;
        }

        // After consuming the stream, metadata should be available
        $metadata = $output->getResult()->getMetadata();
        $this->assertTrue($metadata->has('sources'));
        $this->assertCount(2, $metadata->get('sources'));
        $this->assertSame([$source1, $source2], $metadata->get('sources'));
    }
}

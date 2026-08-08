<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Execution\Runner;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingSignature;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class RunnerTest extends TestCase
{
    public function testWithoutRegisteredToolsTheToolsOptionStaysUntouched()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);

        $options = $this->captureOptions($toolbox, []);

        $this->assertSame([], $options);
    }

    public function testRegisteredToolsAreExposedAsToolsOption()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $options = $this->captureOptions($toolbox, []);

        $this->assertSame(['tools' => [$tool1, $tool2]], $options);
    }

    public function testRegisteredToolsGetFilteredByTheToolsOption()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $options = $this->captureOptions($toolbox, ['tools' => ['tool2']]);

        $this->assertSame(['tools' => [$tool2]], $options);
    }

    public function testToolCallMessagesEndUpInTheCallersMessageBag()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $messageBag = new MessageBag();

        $runner = $this->createRunner($this->platform(new ToolCallResult([$toolCall])), $toolbox, excludeToolMessages: false);
        $runner->run($this->createStub(AgentInterface::class), 'gpt-4', $messageBag, []);

        $this->assertCount(2, $messageBag);
        $this->assertInstanceOf(AssistantMessage::class, $messageBag->getMessages()[0]);
        $this->assertInstanceOf(ToolCallMessage::class, $messageBag->getMessages()[1]);
    }

    public function testToolCallMessagesAreKeptOutOfTheCallersMessageBagWhenExcluded()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $messageBag = new MessageBag();

        $runner = $this->createRunner($this->platform(new ToolCallResult([$toolCall])), $toolbox, excludeToolMessages: true);
        $runner->run($this->createStub(AgentInterface::class), 'gpt-4', $messageBag, []);

        $this->assertCount(0, $messageBag);
    }

    public function testMultiPartResultContainingAToolCallGetsExecuted()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $result = new MultiPartResult([
            new TextResult('Some text before tool call'),
            new ToolCallResult([$toolCall]),
        ]);

        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn(new TextResult('Final response'));

        $runner = $this->createRunner($this->platform($result), $toolbox);
        $actual = $runner->run($agent, 'gpt-4', new MessageBag(), []);

        $this->assertInstanceOf(TextResult::class, $actual);
        $this->assertSame('Final response', $actual->getContent());
    }

    public function testThinkingIsPreservedInAssistantToolCallMessage()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $result = new MultiPartResult([
            new ThinkingResult('I should use a tool.', 'sig_123'),
            new ToolCallResult([$toolCall]),
        ]);
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn(new TextResult('Final response'));
        $messages = new MessageBag();

        $runner = $this->createRunner($this->platform($result), $toolbox);
        $runner->run($agent, 'claude', $messages, []);

        $assistantMessage = $messages->getMessages()[0];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $content = $assistantMessage->getContent();
        $this->assertCount(2, $content);
        $this->assertInstanceOf(Thinking::class, $content[0]);
        $this->assertSame('I should use a tool.', $content[0]->getContent());
        $this->assertSame('sig_123', $content[0]->getSignature());
        $this->assertSame($toolCall, $content[1]);
    }

    public function testMultiPartResultWithSeveralToolCallPartsExecutesAllOfThem()
    {
        $toolCall1 = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'tool2', ['arg2' => 'value2']);

        $executed = [];
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(static function (ToolCall $toolCall) use (&$executed): ToolResult {
                $executed[] = $toolCall->getName();

                return new ToolResult($toolCall, 'Test response');
            });

        $result = new MultiPartResult([
            new TextResult('Some text before tool calls'),
            new ToolCallResult([$toolCall1]),
            new ToolCallResult([$toolCall2]),
        ]);

        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn(new TextResult('Final response'));

        $runner = $this->createRunner($this->platform($result), $toolbox);
        $runner->run($agent, 'gpt-4', new MessageBag(), []);

        $this->assertSame(['tool1', 'tool2'], $executed);
    }

    public function testMultiPartResultWithoutToolCallIsReturnedAsIs()
    {
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->expects($this->never())->method('execute');

        $result = new MultiPartResult([
            new TextResult('Some text'),
            new TextResult('More text'),
        ]);

        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->never())->method('call');

        $runner = $this->createRunner($this->platform($result), $toolbox);
        $actual = $runner->run($agent, 'gpt-4', new MessageBag(), []);

        $this->assertSame($result, $actual);
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
            ->willReturn(new ToolResult($toolCall, 'Response based on the two articles.', new SourceCollection([$source1, $source2])));

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturn(new TextResult('Final response based on the two articles.'));

        $runner = $this->createRunner($this->platform(new ToolCallResult([$toolCall])), $toolbox, includeSources: true);
        $result = $runner->run($agent, 'gpt-4', new MessageBag(), []);

        $metadata = $result->getMetadata();
        $this->assertTrue($metadata->has('sources'));
        $this->assertInstanceOf(SourceCollection::class, $sources = $metadata->get('sources'));
        $this->assertCount(2, $sources);
        $this->assertSame([$source1, $source2], iterator_to_array($sources));
    }

    public function testSourcesDoNotEndUpInResultMetadataWithSettingOff()
    {
        $toolCall = new ToolCall('call_1234', 'tool_sources', ['arg1' => 'value1']);
        $source = new Source('Relevant Article 1', 'http://example.com/article1', 'Content of article about the topic');
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Response based on the article.', new SourceCollection([$source])));

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturn(new TextResult('Final response based on the article.'));

        $runner = $this->createRunner($this->platform(new ToolCallResult([$toolCall])), $toolbox, includeSources: false);
        $result = $runner->run($agent, 'gpt-4', new MessageBag(), []);

        $this->assertFalse($result->getMetadata()->has('sources'));
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
                new ToolResult($toolCall1, 'Response based on the first article.', new SourceCollection([$source1])),
                new ToolResult($toolCall2, 'Response based on the second article.', new SourceCollection([$source2])),
            );

        $platform = $this->platform(
            new ToolCallResult([$toolCall1]),
            new ToolCallResult([$toolCall2]),
            new TextResult('Final response based on both articles.'),
        );

        $agent = new Agent($platform, 'foo-bar', toolbox: $toolbox, includeSources: true);
        $result = $agent->call(new MessageBag());

        $metadata = $result->getMetadata();
        $this->assertTrue($metadata->has('sources'));
        $this->assertInstanceOf(SourceCollection::class, $sources = $metadata->get('sources'));
        $this->assertCount(2, $sources);
        $this->assertSame([$source1, $source2], iterator_to_array($sources));
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
            ->willReturn(new ToolResult($toolCall, 'Response based on the two articles.', new SourceCollection([$source1, $source2])));

        $stream = new StreamResult((static function () use ($toolCall) {
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
            yield new ToolCallComplete([$toolCall]);
        })());

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturn(new TextResult('Final response based on the two articles.'));

        $runner = $this->createRunner($this->platform($stream), $toolbox, includeSources: true);
        $result = $runner->run($agent, 'gpt-4', new MessageBag(), []);
        iterator_to_array($result->getContent());

        $metadata = $result->getMetadata();
        $this->assertTrue($metadata->has('sources'));
        $this->assertInstanceOf(SourceCollection::class, $sources = $metadata->get('sources'));
        $this->assertCount(2, $sources);
        $this->assertSame([$source1, $source2], iterator_to_array($sources));
    }

    public function testMetadataGetsPropagatedInStreamingWithToolCalls()
    {
        $toolCall = new ToolCall('call_meta_1', 'tool_meta', ['foo' => 'bar']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Tool responded'));

        $stream = new StreamResult((static function () use ($toolCall) {
            yield new TextDelta('partial-1');
            yield new TextDelta('partial-2');
            yield new ToolCallComplete([$toolCall]);
        })());

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (): TextResult {
                $final = new TextResult('Final content after tool');
                $final->getMetadata()->add('foo', 'bar');

                return $final;
            });

        $runner = $this->createRunner($this->platform($stream), $toolbox);
        $result = $runner->run($agent, 'gpt-4', new MessageBag(), []);
        iterator_to_array($result->getContent());

        $metadata = $result->getMetadata();
        $this->assertTrue($metadata->has('foo'));
        $this->assertSame('bar', $metadata->get('foo'));
    }

    public function testStreamedToolCallWithoutPrecedingTextDoesNotAddEmptyAssistantMessage()
    {
        $toolCall = new ToolCall('call_empty', 'tool_empty', ['foo' => 'bar']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Tool responded'));

        // Streamed response that issues a tool call without any preceding text delta.
        $stream = new StreamResult((static function () use ($toolCall) {
            yield new ToolCallComplete([$toolCall]);
        })());

        $capturedMessages = null;
        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (MessageBag $messages) use (&$capturedMessages): TextResult {
                $capturedMessages = $messages;

                return new TextResult('Final response');
            });

        $runner = $this->createRunner($this->platform($stream), $toolbox);
        $result = $runner->run($agent, 'gpt-4', new MessageBag(), []);
        iterator_to_array($result->getContent());

        $this->assertNotNull($capturedMessages);
        foreach ($capturedMessages->getMessages() as $message) {
            if ($message instanceof AssistantMessage && !$message->hasToolCalls() && '' === (string) $message->asText()) {
                $this->fail('An empty assistant message must not be added to the message bag when the streamed tool call has no preceding text.');
            }
        }
    }

    public function testStreamedToolCallWithPrecedingTextKeepsAssistantMessage()
    {
        $toolCall = new ToolCall('call_text', 'tool_text', ['foo' => 'bar']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Tool responded'));

        $stream = new StreamResult((static function () use ($toolCall) {
            yield new TextDelta('Let me ');
            yield new TextDelta('check that.');
            yield new ToolCallComplete([$toolCall]);
        })());

        $capturedMessages = null;
        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (MessageBag $messages) use (&$capturedMessages): TextResult {
                $capturedMessages = $messages;

                return new TextResult('Final response');
            });

        $runner = $this->createRunner($this->platform($stream), $toolbox);
        $result = $runner->run($agent, 'gpt-4', new MessageBag(), []);
        iterator_to_array($result->getContent());

        $this->assertInstanceOf(MessageBag::class, $capturedMessages);
        $assistantMessages = array_values(array_filter(
            $capturedMessages->getMessages(),
            static fn ($message): bool => $message instanceof AssistantMessage,
        ));
        $this->assertCount(1, $assistantMessages);
        $this->assertTrue($assistantMessages[0]->hasToolCalls());
        $this->assertSame('Let me check that.', $assistantMessages[0]->asText());
    }

    public function testStreamedThinkingIsPreservedInAssistantToolCallMessage()
    {
        $toolCall1 = new ToolCall('call_1', 'tool_1');
        $toolCall2 = new ToolCall('call_2', 'tool_2');
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(static fn (ToolCall $toolCall): ToolResult => new ToolResult($toolCall, 'Test response'));

        $stream = new StreamResult((static function () use ($toolCall1, $toolCall2) {
            yield new ThinkingStart();
            yield new ThinkingSignature('opaque_sig');
            yield new ThinkingComplete('', 'opaque_sig');
            yield new ToolCallStart($toolCall1->getId(), $toolCall1->getName());
            yield new ThinkingStart();
            yield new ThinkingDelta('A visible summary.');
            yield new ThinkingSignature('summary_sig');
            yield new ThinkingComplete('A visible summary.', 'summary_sig');
            yield new ToolCallStart($toolCall2->getId(), $toolCall2->getName());
            yield new ToolCallComplete([$toolCall1, $toolCall2]);
        })());

        $capturedMessages = null;
        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (MessageBag $messages) use (&$capturedMessages): TextResult {
                $capturedMessages = $messages;

                return new TextResult('Final response');
            });

        $runner = $this->createRunner($this->platform($stream), $toolbox);
        $result = $runner->run($agent, 'claude', new MessageBag(), []);
        iterator_to_array($result->getContent());

        $this->assertInstanceOf(MessageBag::class, $capturedMessages);
        $assistantMessage = $capturedMessages->getMessages()[0];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $content = $assistantMessage->getContent();
        $this->assertCount(4, $content);
        $this->assertInstanceOf(Thinking::class, $content[0]);
        $this->assertSame('', $content[0]->getContent());
        $this->assertSame('opaque_sig', $content[0]->getSignature());
        $this->assertSame($toolCall1, $content[1]);
        $this->assertInstanceOf(Thinking::class, $content[2]);
        $this->assertSame('A visible summary.', $content[2]->getContent());
        $this->assertSame('summary_sig', $content[2]->getSignature());
        $this->assertSame($toolCall2, $content[3]);
    }

    public function testUsageMetadataGetsPropagatedInStreaming()
    {
        $toolCall = new ToolCall('call_meta_1', 'tool_meta', ['foo' => 'bar']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Tool responded'));

        $stream = new StreamResult((static function () use ($toolCall) {
            yield new TextDelta('partial-1');
            yield new TextDelta('partial-2');
            yield new ToolCallComplete([$toolCall]);
        })());
        $stream->getMetadata()->add('token_usage', new TokenUsage(totalTokens: 10));

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (): TextResult {
                $toolResult = new TextResult('Final content after tool');
                $toolResult->getMetadata()->add('token_usage', new TokenUsage(totalTokens: 10));

                return $toolResult;
            });

        $runner = $this->createRunner($this->platform($stream), $toolbox);
        $result = $runner->run($agent, 'gpt-4', new MessageBag(), []);
        iterator_to_array($result->getContent());

        $usage = $result->getMetadata()->get('token_usage');
        $this->assertInstanceOf(TokenUsageAggregation::class, $usage);
        $this->assertSame(20, $usage->getTotalTokens());
    }

    public function testThrowsExceptionWhenMaxIterationsExceeded()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->method('call')
            ->willReturn(new ToolCallResult([$toolCall])); // Always returns tool call, causing infinite loop

        $runner = $this->createRunner($this->platform(new ToolCallResult([$toolCall])), $toolbox, maxToolCalls: 3);

        $this->expectException(MaxIterationsExceededException::class);
        $this->expectExceptionMessage('Maximum number of tool calling iterations (3) exceeded.');

        $runner->run($agent, 'gpt-4', new MessageBag(), []);
    }

    public function testCustomMaxIterationsLimitAllowsConfiguredIterations()
    {
        $toolCall1 = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'tool2', ['arg1' => 'value2']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                new ToolResult($toolCall1, 'Response 1'),
                new ToolResult($toolCall2, 'Response 2'),
            );

        $agent = $this->createMock(AgentInterface::class);
        $agent
            ->expects($this->exactly(2))
            ->method('call')
            ->willReturnOnConsecutiveCalls(
                new ToolCallResult([$toolCall2]),
                new TextResult('Final response after two tool calls.'),
            );

        // Allow up to 5 iterations, we only need 2
        $runner = $this->createRunner($this->platform(new ToolCallResult([$toolCall1])), $toolbox, maxToolCalls: 5);
        $result = $runner->run($agent, 'gpt-4', new MessageBag(), []);

        $this->assertInstanceOf(TextResult::class, $result);
    }

    public function testSourcesAreResetAfterMaxIterationsException()
    {
        $failingToolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $successfulToolCall = new ToolCall('id2', 'tool2', ['arg1' => 'value2']);
        $source = new Source('Relevant Article 1', 'http://example.com/article1', 'Content of article about the topic');
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                new ToolResult($failingToolCall, 'Response 1', new SourceCollection([$source])),
                new ToolResult($successfulToolCall, 'Response 3', new SourceCollection([$source])),
            );

        $platform = $this->platform(
            new ToolCallResult([$failingToolCall]),
            new ToolCallResult([$successfulToolCall]),
        );
        $runner = $this->createRunner($platform, $toolbox, maxToolCalls: 1, includeSources: true);

        $failingAgent = $this->createMock(AgentInterface::class);
        $failingAgent
            ->method('call')
            ->willReturn(new ToolCallResult([$failingToolCall]));

        try {
            $runner->run($failingAgent, 'gpt-4', new MessageBag(), []);
            $this->fail('Expected MaxIterationsExceededException to be thrown.');
        } catch (MaxIterationsExceededException) {
        }

        $successfulAgent = $this->createMock(AgentInterface::class);
        $successfulAgent
            ->expects($this->once())
            ->method('call')
            ->willReturn(new TextResult('Final response'));

        $result = $runner->run($successfulAgent, 'gpt-4', new MessageBag(), []);

        $metadata = $result->getMetadata();
        $this->assertTrue($metadata->has('sources'));
        $this->assertCount(1, $metadata->get('sources'));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function captureOptions(ToolboxInterface $toolbox, array $options): array
    {
        $captured = [];
        $platform = new InMemoryPlatform(static function (mixed $model, mixed $input, array $invocationOptions) use (&$captured): TextResult {
            $captured = $invocationOptions;

            return new TextResult('Done');
        });

        $runner = $this->createRunner($platform, $toolbox);
        $runner->run($this->createStub(AgentInterface::class), 'gpt-4', new MessageBag(), $options);

        return $captured;
    }

    private function createRunner(
        PlatformInterface $platform,
        ToolboxInterface $toolbox,
        ?int $maxToolCalls = 50,
        bool $excludeToolMessages = false,
        bool $includeSources = false,
    ): Runner {
        return new Runner(
            $platform,
            $toolbox,
            new SequentialToolExecutor($toolbox),
            $maxToolCalls,
            $excludeToolMessages,
            $includeSources,
        );
    }

    private function platform(ResultInterface ...$results): InMemoryPlatform
    {
        $invocation = 0;

        return new InMemoryPlatform(static function () use (&$invocation, $results): ResultInterface {
            if (!isset($results[$invocation])) {
                throw new \LogicException(\sprintf('The platform was invoked %d times, but only %d results are configured.', $invocation + 1, \count($results)));
            }

            return $results[$invocation++];
        });
    }
}

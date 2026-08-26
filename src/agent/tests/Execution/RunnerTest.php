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
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Execution\Runner;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\Serializer;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
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

        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Final response'));
        $this->drive($this->createRunner($platform, $toolbox, excludeToolMessages: false), $messageBag);

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

        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Final response'));
        $this->drive($this->createRunner($platform, $toolbox, excludeToolMessages: true), $messageBag);

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

        $platform = $this->platform($result, new TextResult('Final response'));
        $actual = $this->drive($this->createRunner($platform, $toolbox), new MessageBag());

        $this->assertInstanceOf(TextResult::class, $actual);
        $this->assertSame('Final response', $actual->getContent());
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

        $platform = $this->platform($result, new TextResult('Final response'));
        $this->drive($this->createRunner($platform, $toolbox), new MessageBag());

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

        $actual = $this->drive($this->createRunner($this->platform($result), $toolbox), new MessageBag());

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

        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Final response based on the two articles.'));
        $result = $this->drive($this->createRunner($platform, $toolbox, includeSources: true), new MessageBag());

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

        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Final response based on the article.'));
        $result = $this->drive($this->createRunner($platform, $toolbox, includeSources: false), new MessageBag());

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

        $result = $this->drive($this->createRunner($platform, $toolbox, includeSources: true), new MessageBag());

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

        $platform = $this->platform($stream, new TextResult('Final response based on the two articles.'));
        $result = $this->drive($this->createRunner($platform, $toolbox, includeSources: true), new MessageBag());

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

        $final = new TextResult('Final content after tool');
        $final->getMetadata()->add('foo', 'bar');

        $result = $this->drive($this->createRunner($this->platform($stream, $final), $toolbox), new MessageBag());

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

        $messages = new MessageBag();
        $platform = $this->platform($stream, new TextResult('Final response'));
        $this->drive($this->createRunner($platform, $toolbox), $messages);

        foreach ($messages->getMessages() as $message) {
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

        $messages = new MessageBag();
        $platform = $this->platform($stream, new TextResult('Final response'));
        $this->drive($this->createRunner($platform, $toolbox), $messages);

        $textMessages = array_filter(
            $messages->getMessages(),
            static fn ($message): bool => $message instanceof AssistantMessage && !$message->hasToolCalls(),
        );
        $this->assertCount(1, $textMessages);
        $this->assertSame('Let me check that.', reset($textMessages)->asText());
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

        $final = new TextResult('Final content after tool');
        $final->getMetadata()->add('token_usage', new TokenUsage(totalTokens: 10));

        $result = $this->drive($this->createRunner($this->platform($stream, $final), $toolbox), new MessageBag());

        $usage = $result->getMetadata()->get('token_usage');
        $this->assertInstanceOf(TokenUsageAggregation::class, $usage);
        $this->assertSame(20, $usage->getTotalTokens());
    }

    public function testStreamedStructuredOutputEndsWithTheObjectResult()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);

        // the platform's structured output converter attaches this listener to a streamed response_format call
        $stream = new StreamResult((static function () {
            yield new TextDelta('{"title":"Pan');
            yield new TextDelta('cakes","minutes":20}');
        })(), [new PartialObjectStreamListener(new Serializer(), StreamedRecipe::class)]);

        $result = $this->drive($this->createRunner($this->platform($stream), $toolbox), new MessageBag(), ['stream' => true, 'response_format' => StreamedRecipe::class]);

        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertInstanceOf(StreamedRecipe::class, $recipe = $result->getContent());
        $this->assertSame('Pancakes', $recipe->title);
        $this->assertSame(20, $recipe->minutes);
    }

    public function testThrowsExceptionWhenMaxIterationsExceeded()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        // the model keeps asking for tools, which would loop forever without a cap
        $platform = new InMemoryPlatform(static fn (): ToolCallResult => new ToolCallResult([$toolCall]));
        $runner = $this->createRunner($platform, $toolbox, maxToolCalls: 3);

        $this->expectException(MaxIterationsExceededException::class);
        $this->expectExceptionMessage('Maximum number of tool calling iterations (3) exceeded.');

        $this->drive($runner, new MessageBag());
    }

    public function testThrowsExceptionWhenMaxIterationsExceededWhileStreaming()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        // the model keeps asking for tools, the platform provides more rounds than the limit allows
        $streams = array_map(static fn (): StreamResult => new StreamResult((static function () use ($toolCall) {
            yield new ToolCallComplete([$toolCall]);
        })()), range(1, 10));

        $runner = $this->createRunner($this->platform(...$streams), $toolbox, maxToolCalls: 3);

        $this->expectException(MaxIterationsExceededException::class);
        $this->expectExceptionMessage('Maximum number of tool calling iterations (3) exceeded.');

        $this->drive($runner, new MessageBag(), ['stream' => true]);
    }

    public function testMaxIterationsLimitIsNotSharedBetweenConcurrentStreams()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $executions = 0;
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnCallback(static function () use ($toolCall, &$executions): ToolResult {
                ++$executions;

                return new ToolResult($toolCall, 'Test response');
            });

        // enough streams for both runs to exhaust a full budget of their own
        $streams = array_map(static fn (): StreamResult => new StreamResult((static function () use ($toolCall) {
            yield new ToolCallComplete([$toolCall]);
        })()), range(1, 20));

        $runner = $this->createRunner($this->platform(...$streams), $toolbox, maxToolCalls: 3);

        // both runs are started before either one is consumed, so the tool calling loop of the
        // first one only begins once the second run was already created
        $first = $runner->run('gpt-4', new MessageBag(), ['stream' => true]);
        $second = $runner->run('gpt-4', new MessageBag(), ['stream' => true]);

        // each run has to spend a full budget of its own before it gets capped
        $expectedExecutions = 0;
        foreach ([$first, $second] as $run) {
            try {
                iterator_to_array($run, false);
                $this->fail('Expected MaxIterationsExceededException to be thrown.');
            } catch (MaxIterationsExceededException) {
            }

            $expectedExecutions += 3;
            $this->assertSame($expectedExecutions, $executions);
        }
    }

    public function testMaxIterationsLimitAppliesPerRun()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $platform = $this->platform(
            new ToolCallResult([$toolCall]),
            new TextResult('First response'),
            new ToolCallResult([$toolCall]),
            new TextResult('Second response'),
        );
        $runner = $this->createRunner($platform, $toolbox, maxToolCalls: 1);

        $this->assertSame('First response', $this->drive($runner, new MessageBag())->getContent());
        $this->assertSame('Second response', $this->drive($runner, new MessageBag())->getContent());
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

        $platform = $this->platform(
            new ToolCallResult([$toolCall1]),
            new ToolCallResult([$toolCall2]),
            new TextResult('Final response after two tool calls.'),
        );

        // Allow up to 5 iterations, we only need 2
        $result = $this->drive($this->createRunner($platform, $toolbox, maxToolCalls: 5), new MessageBag());

        $this->assertInstanceOf(TextResult::class, $result);
    }

    public function testSourcesDoNotLeakFromAFailedRunIntoTheNextOne()
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
                new ToolResult($successfulToolCall, 'Response 2', new SourceCollection([$source])),
            );

        $platform = $this->platform(
            // first run: the model keeps asking for tools until the cap is hit
            new ToolCallResult([$failingToolCall]),
            new ToolCallResult([$failingToolCall]),
            // second run: one tool call, then an answer
            new ToolCallResult([$successfulToolCall]),
            new TextResult('Final response'),
        );
        $runner = $this->createRunner($platform, $toolbox, maxToolCalls: 1, includeSources: true);

        try {
            $this->drive($runner, new MessageBag());
            $this->fail('Expected MaxIterationsExceededException to be thrown.');
        } catch (MaxIterationsExceededException) {
        }

        $result = $this->drive($runner, new MessageBag());

        $metadata = $result->getMetadata();
        $this->assertTrue($metadata->has('sources'));
        $this->assertCount(1, $metadata->get('sources'));
    }

    public function testItYieldsProgressUpdatesForTheModelRequestAndEachToolCall()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Final response'));
        $updates = $this->collectUpdates($this->createRunner($platform, $toolbox), new MessageBag());

        $stages = array_map(
            static fn (UpdateInterface $update): string => $update instanceof Progress ? $update->getStage() : $update->getType()->value,
            $updates,
        );

        $this->assertSame(['model_request', 'tool_call', 'model_request', 'result'], $stages);
    }

    public function testItYieldsEveryStreamedDeltaAsAProgressUpdate()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);

        $stream = new StreamResult((static function () {
            yield new TextDelta('Hello ');
            yield new TextDelta('world!');
        })());

        $updates = $this->collectUpdates($this->createRunner($this->platform($stream), $toolbox), new MessageBag());

        $deltas = array_values(array_filter(
            $updates,
            static fn (UpdateInterface $update): bool => $update instanceof Progress && 'delta' === $update->getStage(),
        ));

        $this->assertCount(2, $deltas);
        $this->assertSame('Hello ', $deltas[0]->getPayload()->getText());
        $this->assertSame('world!', $deltas[1]->getPayload()->getText());

        $result = $this->drive($this->createRunner($this->platform(new StreamResult((static function () {
            yield new TextDelta('Hello ');
            yield new TextDelta('world!');
        })())), $toolbox), new MessageBag());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world!', $result->getContent());
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

        $this->drive($this->createRunner($platform, $toolbox), new MessageBag(), $options);

        return $captured;
    }

    /**
     * Drives the runner to completion and returns its final result.
     *
     * @param array<string, mixed> $options
     */
    private function drive(Runner $runner, MessageBag $messages, array $options = []): ResultInterface
    {
        foreach ($runner->run('gpt-4', $messages, $options) as $update) {
            if ($update instanceof ResultUpdate) {
                return $update->getResult();
            }
        }

        throw new \LogicException('The runner did not produce a result.');
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<UpdateInterface>
     */
    private function collectUpdates(Runner $runner, MessageBag $messages, array $options = []): array
    {
        return iterator_to_array($runner->run('gpt-4', $messages, $options), false);
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

final class StreamedRecipe
{
    public string $title;
    public int $minutes;
}

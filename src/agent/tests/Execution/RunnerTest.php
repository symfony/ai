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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Execution\ExecutionState;
use Symfony\AI\Agent\Execution\ExecutionStatus;
use Symfony\AI\Agent\Execution\Runner;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\CommentaryResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryComplete;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingSignature;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\Serializer;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class RunnerTest extends TestCase
{
    /**
     * @var list<MessageBag>
     */
    private array $invokedMessages = [];

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

        $this->assertSame(['tools' => [$this->functionTool('tool1', 'description1'), $this->functionTool('tool2', 'description2')]], $options);
    }

    public function testRegisteredToolsGetFilteredByTheToolsOption()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $options = $this->captureOptions($toolbox, ['tools' => ['tool2']]);

        $this->assertSame(['tools' => [$this->functionTool('tool2', 'description2')]], $options);
    }

    public function testAnEmptyToolsOptionExposesNoRegisteredTool()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $options = $this->captureOptions($toolbox, ['tools' => []]);

        $this->assertSame(['tools' => []], $options);
    }

    public function testToolOmittedFromTheToolsOptionIsNotExecuted()
    {
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);
        $toolbox->expects($this->never())->method('execute');

        $platform = $this->platform(new ToolCallResult([new ToolCall('id1', 'tool1')]));

        $this->expectException(ToolNotFoundException::class);
        $this->expectExceptionMessage('Tool not found for call: tool1.');

        $this->drive($this->createRunner($platform, $toolbox), new MessageBag(), ['tools' => ['tool2']]);
    }

    public function testNoToolIsExecutedWithAnEmptyToolsOption()
    {
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([$tool1]);
        $toolbox->expects($this->never())->method('execute');

        $platform = $this->platform(new ToolCallResult([new ToolCall('id1', 'tool1')]));

        $this->expectException(ToolNotFoundException::class);

        $this->drive($this->createRunner($platform, $toolbox), new MessageBag(), ['tools' => []]);
    }

    public function testNoToolOfABatchIsExecutedWhenOneIsNotAllowed()
    {
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);
        $toolbox->expects($this->never())->method('execute');

        $platform = $this->platform(new ToolCallResult([new ToolCall('id1', 'tool2'), new ToolCall('id2', 'tool1')]));

        $this->expectException(ToolNotFoundException::class);
        $this->expectExceptionMessage('Tool not found for call: tool1.');

        $this->drive($this->createRunner($platform, $toolbox), new MessageBag(), ['tools' => ['tool2']]);
    }

    public function testUnknownToolIsLeftToTheToolboxWithToolsOption()
    {
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $toolCall = new ToolCall('id1', 'unknown');

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([$tool1]);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->with($toolCall)
            ->willReturn(new ToolResult($toolCall, 'Tool "unknown" was not found'));

        $messageBag = new MessageBag();
        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Final response'));

        $result = $this->drive($this->createRunner($platform, $toolbox), $messageBag, ['tools' => ['tool1']]);

        $this->assertSame('Final response', $result->getContent());
        $toolCallMessage = $this->lastInvokedMessages()->getMessages()[1];
        $this->assertInstanceOf(ToolCallMessage::class, $toolCallMessage);
        $this->assertSame('Tool "unknown" was not found', $toolCallMessage->asText());
    }

    public function testToolRegisteredDuringTheRunIsNotExecutedWhenNotAllowed()
    {
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturnOnConsecutiveCalls([$tool1], [$tool1, $tool2]);
        $toolbox->expects($this->never())->method('execute');

        $platform = $this->platform(new ToolCallResult([new ToolCall('id1', 'tool2')]));

        $this->expectException(ToolNotFoundException::class);
        $this->expectExceptionMessage('Tool not found for call: tool2.');

        $this->drive($this->createRunner($platform, $toolbox), new MessageBag(), ['tools' => ['tool1']]);
    }

    public function testToolRegisteredAfterAnEmptyListingIsNotExecutedWhenNotAllowed()
    {
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturnOnConsecutiveCalls([], [$tool1, $tool2]);
        $toolbox->expects($this->never())->method('execute');

        $platform = $this->platform(new ToolCallResult([new ToolCall('id1', 'tool2')]));

        $this->expectException(ToolNotFoundException::class);
        $this->expectExceptionMessage('Tool not found for call: tool2.');

        $this->drive($this->createRunner($platform, $toolbox), new MessageBag(), ['tools' => ['tool1']]);
    }

    public function testStreamedToolCallOmittedFromTheToolsOptionIsNotExecuted()
    {
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);
        $toolbox->expects($this->never())->method('execute');

        $stream = new StreamResult((static function () {
            yield new ToolCallComplete([new ToolCall('id1', 'tool1')]);
        })());

        $platform = $this->platform($stream);

        $this->expectException(ToolNotFoundException::class);

        $this->drive($this->createRunner($platform, $toolbox), new MessageBag(), ['tools' => ['tool2']]);
    }

    public function testRegisteredToolsAreExecutedWithoutToolsOption()
    {
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolCall = new ToolCall('id1', 'tool1');

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->with($toolCall)
            ->willReturn(new ToolResult($toolCall, 'Tool1 response'));

        $messageBag = new MessageBag();
        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Final response'));

        $this->drive($this->createRunner($platform, $toolbox), $messageBag);

        $toolCallMessage = $this->lastInvokedMessages()->getMessages()[1];
        $this->assertInstanceOf(ToolCallMessage::class, $toolCallMessage);
        $this->assertSame('Tool1 response', $toolCallMessage->asText());
    }

    public function testRegisteredToolsAreExecutedWithOnlyServerToolsInTheToolsOption()
    {
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $toolCall = new ToolCall('id1', 'tool1');

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([$tool1]);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->with($toolCall)
            ->willReturn(new ToolResult($toolCall, 'Tool1 response'));

        $messageBag = new MessageBag();
        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Final response'));

        $this->drive($this->createRunner($platform, $toolbox), $messageBag, ['tools' => [['type' => 'web_search']]]);

        $toolCallMessage = $this->lastInvokedMessages()->getMessages()[1];
        $this->assertInstanceOf(ToolCallMessage::class, $toolCallMessage);
        $this->assertSame('Tool1 response', $toolCallMessage->asText());
    }

    public function testServerToolsInTheToolsOptionAreKeptNextToRegisteredTools()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $serverTool = ['type' => 'web_fetch_20260318', 'name' => 'web_fetch'];

        $options = $this->captureOptions($toolbox, ['tools' => [$serverTool]]);

        $this->assertSame(['tools' => [$this->functionTool('tool1', 'description1'), $this->functionTool('tool2', 'description2'), $serverTool]], $options);
    }

    public function testServerToolsAreKeptWhileRegisteredToolsGetFilteredByName()
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $tool1 = new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null);
        $tool2 = new Tool(new ExecutionReference('ClassTool2', 'method1'), 'tool2', 'description2', null);
        $toolbox->method('getTools')->willReturn([$tool1, $tool2]);

        $serverTool = ['type' => 'web_fetch_20260318', 'name' => 'web_fetch'];

        $options = $this->captureOptions($toolbox, ['tools' => ['tool2', $serverTool]]);

        $this->assertSame(['tools' => [$this->functionTool('tool2', 'description2'), $serverTool]], $options);
    }

    public function testToolCallMessagesReachTheNextRoundButNotTheCallersMessageBag()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $messageBag = new MessageBag(Message::ofUser('Hello'));
        $roles = [];
        $results = [new ToolCallResult([$toolCall]), new TextResult('Final response')];
        $platform = MockPlatformFactory::createPlatform(static function ($model, array $input) use (&$roles, &$results): ResultInterface {
            $roles[] = array_column($input['messages'], 'role');

            return array_shift($results);
        });

        $this->drive($this->createRunner($platform, $toolbox), $messageBag);

        $this->assertSame([['user'], ['user', 'assistant', 'tool']], $roles);
        $this->assertCount(1, $messageBag);
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

    public function testToolCallAlongsideBinaryOutputStillGetsExecuted()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        // Gemini returns inlineData next to a functionCall, the Responses API an image_generation_call
        $result = new MultiPartResult([
            new BinaryResult('binary-image-bytes', 'image/png'),
            new ToolCallResult([$toolCall]),
        ]);

        $messages = new MessageBag();
        $platform = $this->platform($result, new TextResult('Final response'));
        $actual = $this->drive($this->createRunner($platform, $toolbox), $messages);

        $this->assertInstanceOf(TextResult::class, $actual);

        $assistantMessage = $this->lastInvokedMessages()->getMessages()[0];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame([$toolCall], $assistantMessage->getToolCalls());
        $this->assertInstanceOf(File::class, $assistantMessage->getContent()[0]);
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

    public function testSourcesEndUpOnTheAnswerWithSettingOn()
    {
        $toolCall = new ToolCall('call_1234', 'tool_sources');
        $source = new Source('Relevant Article', 'http://example.com/article', 'Content of article about the topic');
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('execute')->willReturn(new ToolResult($toolCall, 'Response based on the article.', new SourceCollection([$source])));

        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Final response based on the article.'));
        $updates = $this->collectUpdates($this->createRunner($platform, $toolbox, includeSources: true), new MessageBag());

        $resultUpdate = end($updates);
        $this->assertInstanceOf(ResultUpdate::class, $resultUpdate);
        $answer = $resultUpdate->getMessages()?->getMessages()[2];
        $this->assertInstanceOf(AssistantMessage::class, $answer);
        $this->assertSame([$source], iterator_to_array($answer->getMetadata()->get('sources')));
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

        $assistantMessages = array_values(array_filter(
            $this->lastInvokedMessages()->getMessages(),
            static fn ($message): bool => $message instanceof AssistantMessage,
        ));

        $this->assertCount(1, $assistantMessages);
        $this->assertSame('Let me check that.', $assistantMessages[0]->asText());
        $this->assertSame([$toolCall], $assistantMessages[0]->getToolCalls());
        $this->assertEquals(
            [new Text('Let me check that.'), $toolCall],
            $assistantMessages[0]->getContent(),
        );
    }

    public function testAStreamedRoundReturnsTheSameResultABufferedOneWould()
    {
        $stream = new StreamResult((static function () {
            yield new ThinkingDelta('let me think');
            yield new ThinkingSignature('sig-abc');
            yield new TextDelta('It is 10:00.');
        })());

        $actual = $this->drive($this->createRunner($this->platform($stream), $this->createMock(ToolboxInterface::class)), new MessageBag());

        // flattening the turn to its text drops the signature providers require on the next turn
        $this->assertInstanceOf(MultiPartResult::class, $actual);
        $this->assertEquals(
            [new ThinkingResult('let me think', 'sig-abc'), new TextResult('It is 10:00.')],
            $actual->getContent(),
        );
        $this->assertSame('It is 10:00.', $actual->asText());
    }

    public function testAStreamedRoundKeepsItsCommentaryLikeABufferedOne()
    {
        $stream = new StreamResult((static function () {
            yield new CommentaryStart();
            yield new CommentaryDelta('I will run code.');
            yield new CommentaryComplete('I will run code.');
            yield new TextDelta('425');
        })());

        $actual = $this->drive($this->createRunner($this->platform($stream), $this->createMock(ToolboxInterface::class)), new MessageBag());

        $this->assertInstanceOf(MultiPartResult::class, $actual);
        $this->assertEquals(
            [new CommentaryResult('I will run code.'), new TextResult('425')],
            $actual->getContent(),
        );
        $this->assertSame('425', $actual->asText());
    }

    public function testAStreamedRoundKeepsThePhaseOfItsAnswerLikeABufferedOne()
    {
        $stream = new StreamResult((static function () {
            yield new CommentaryStart();
            yield new CommentaryDelta('I will run code.');
            yield new CommentaryComplete('I will run code.');
            yield new TextDelta('425', 'final_answer');
        })());

        $actual = $this->drive($this->createRunner($this->platform($stream), $this->createMock(ToolboxInterface::class)), new MessageBag());

        $this->assertInstanceOf(MultiPartResult::class, $actual);
        [$commentary, $answer] = $actual->getContent();
        $this->assertInstanceOf(CommentaryResult::class, $commentary);
        $this->assertInstanceOf(TextResult::class, $answer);
        $this->assertSame('425', $answer->getContent());
        $this->assertSame('final_answer', $answer->getPhase());
    }

    public function testAStreamedRoundOfPlainTextStaysATextResult()
    {
        $stream = new StreamResult((static function () {
            yield new TextDelta('It is ');
            yield new TextDelta('10:00.');
        })());

        $actual = $this->drive($this->createRunner($this->platform($stream), $this->createMock(ToolboxInterface::class)), new MessageBag());

        $this->assertInstanceOf(TextResult::class, $actual);
        $this->assertSame('It is 10:00.', $actual->getContent());
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

    public function testFinalResultKeepsItsOwnMetadataOverTheToolCallRounds()
    {
        $toolCall = new ToolCall('call_1', 'tool', []);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->once())
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Tool responded'));

        $round = new ToolCallResult([$toolCall]);
        $round->getMetadata()->add('finish_reason', new FinishReason(FinishReasonCase::TOOL_CALL, 'tool_calls'));
        $round->getMetadata()->add('token_usage', new TokenUsage(totalTokens: 10));

        $final = new TextResult('Final content after tool');
        $final->getMetadata()->add('finish_reason', new FinishReason(FinishReasonCase::STOP, 'stop'));
        $final->getMetadata()->add('token_usage', new TokenUsage(totalTokens: 5));

        $result = $this->drive($this->createRunner($this->platform($round, $final), $toolbox), new MessageBag());

        $finishReason = $result->getMetadata()->get('finish_reason');
        $this->assertInstanceOf(FinishReason::class, $finishReason);
        $this->assertTrue($finishReason->is(FinishReasonCase::STOP));
        $this->assertSame(15, $result->getMetadata()->get('token_usage')->getTotalTokens());
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
        $platform = MockPlatformFactory::createPlatform(static fn (): ToolCallResult => new ToolCallResult([$toolCall]));
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

    public function testRunsDrivenSideBySideHaveTheirRequestsInFlightBeforeEitherResponseIsRead()
    {
        $events = [];
        $invocations = 0;
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturnCallback(function () use (&$events, &$invocations): DeferredResult {
            $request = ++$invocations;
            $events[] = 'request '.$request;

            $converter = $this->createStub(ResultConverterInterface::class);
            $converter->method('convert')->willReturnCallback(static function () use (&$events, $request): ResultInterface {
                $events[] = 'response '.$request;

                return new TextResult('Answer '.$request);
            });

            return new DeferredResult($converter, new InMemoryRawResult());
        });

        $runner = $this->createRunner($platform, $this->createStub(ToolboxInterface::class));

        $first = $runner->run('gpt-4', new MessageBag(), []);
        $second = $runner->run('gpt-4', new MessageBag(), []);

        // running each generator to its first update sends the request without reading the response
        $first->current();
        $second->current();
        $first->next();
        $second->next();

        $this->assertSame(['request 1', 'request 2', 'response 1', 'response 2'], $events);
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

    public function testMaxRoundsStopsAfterTheToolsOfTheLastRoundRan()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $executions = 0;
        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Never asked'));
        $runner = $this->createRunner($platform, $this->countingToolbox($toolCall, $executions));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['max_rounds' => 1]);

        $this->assertSame(1, $executions);
        $this->assertCount(1, $this->invokedMessages);
        $this->assertInstanceOf(ToolCallResult::class, $update->getResult());
        $messages = $update->getMessages();
        $this->assertInstanceOf(MessageBag::class, $messages);
        $this->assertSame([UserMessage::class, AssistantMessage::class, ToolCallMessage::class], array_map(get_class(...), $messages->getMessages()));
        $this->assertSame(ExecutionStatus::Pending, ExecutionState::statusOf($messages));
    }

    public function testMaxRoundsLargerThanNeededBehavesLikeNoLimit()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $executions = 0;
        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Done'));
        $runner = $this->createRunner($platform, $this->countingToolbox($toolCall, $executions));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['max_rounds' => 5]);

        $this->assertSame('Done', $update->getResult()->getContent());
        $this->assertInstanceOf(MessageBag::class, $update->getMessages());
        $this->assertSame(ExecutionStatus::Completed, ExecutionState::statusOf($update->getMessages()));
    }

    public function testMaxRoundsCountsPerRun()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $executions = 0;
        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Done'));
        $runner = $this->createRunner($platform, $this->countingToolbox($toolCall, $executions));

        $first = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['max_rounds' => 1]);
        $second = $this->lastUpdate($runner, $first->getMessages(), ['max_rounds' => 1]);

        $this->assertSame('Done', $second->getResult()->getContent());
        $this->assertSame(1, $executions);
    }

    public function testDeferToolCallsStopsWithoutExecutingTheTools()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['a' => 1]);
        $executions = 0;
        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Never asked'));
        $runner = $this->createRunner($platform, $this->countingToolbox($toolCall, $executions));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => true]);

        $this->assertSame(0, $executions);
        $this->assertCount(1, $this->invokedMessages);
        $this->assertInstanceOf(ToolCallResult::class, $update->getResult());
        $messages = $update->getMessages();
        $this->assertInstanceOf(MessageBag::class, $messages);
        $this->assertSame([UserMessage::class, AssistantMessage::class], array_map(get_class(...), $messages->getMessages()));
        $this->assertSame(ExecutionStatus::WaitingForInput, ExecutionState::statusOf($messages));
        $this->assertEquals([$toolCall], ExecutionState::pendingToolCallsOf($messages));
    }

    public function testDeferToolCallsByNameRunsTheOtherToolsInTheSameRound()
    {
        $server = new ToolCall('id1', 'server_tool', ['a' => 1]);
        $client = new ToolCall('id2', 'client_tool', []);
        $executed = [];
        $platform = $this->platform(new ToolCallResult([$server, $client]), new TextResult('Never asked'));
        $runner = $this->createRunner($platform, $this->recordingToolbox($executed));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => ['client_tool']]);

        $this->assertSame(['server_tool'], $executed);
        $this->assertCount(1, $this->invokedMessages);
        $messages = $update->getMessages();
        $this->assertInstanceOf(MessageBag::class, $messages);
        $this->assertSame([UserMessage::class, AssistantMessage::class, ToolCallMessage::class], array_map(get_class(...), $messages->getMessages()));
        $assistant = $messages->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistant);
        $this->assertEquals([$server, $client], $assistant->getToolCalls());
        $this->assertSame(ExecutionStatus::WaitingForInput, ExecutionState::statusOf($messages));
        $this->assertEquals([$client], ExecutionState::pendingToolCallsOf($messages));
    }

    public function testDeferToolCallsByClosureReceivesEachCall()
    {
        $server = new ToolCall('id1', 'server_tool', []);
        $client = new ToolCall('id2', 'client_tool', ['ask' => true]);
        $seen = [];
        $executed = [];
        $runner = $this->createRunner($this->platform(new ToolCallResult([$server, $client])), $this->recordingToolbox($executed));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => static function (ToolCall $toolCall) use (&$seen): bool {
            $seen[] = $toolCall->getName();

            return [] !== $toolCall->getArguments();
        }]);

        $this->assertSame(['server_tool', 'client_tool'], $seen);
        $this->assertSame(['server_tool'], $executed);
        $this->assertEquals([$client], ExecutionState::pendingToolCallsOf($update->getMessages()));
    }

    public function testDeferToolCallsTrueStillDefersEveryCall()
    {
        $first = new ToolCall('id1', 'server_tool', []);
        $second = new ToolCall('id2', 'client_tool', []);
        $executed = [];
        $runner = $this->createRunner($this->platform(new ToolCallResult([$first, $second])), $this->recordingToolbox($executed));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => true]);

        $this->assertSame([], $executed);
        $this->assertEquals([$first, $second], ExecutionState::pendingToolCallsOf($update->getMessages()));
    }

    public function testDeferToolCallsFalseDefersNoCall()
    {
        $first = new ToolCall('id1', 'server_tool', []);
        $second = new ToolCall('id2', 'client_tool', []);
        $executed = [];
        $runner = $this->createRunner($this->platform(new ToolCallResult([$first, $second]), new TextResult('Done')), $this->recordingToolbox($executed));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => false]);

        $this->assertSame(['server_tool', 'client_tool'], $executed);
        $this->assertSame('Done', $update->getResult()->getContent());
    }

    public function testARoundWithoutADeferredCallContinuesToTheFinalAnswer()
    {
        $toolCall = new ToolCall('id1', 'server_tool', []);
        $executed = [];
        $runner = $this->createRunner($this->platform(new ToolCallResult([$toolCall]), new TextResult('Done')), $this->recordingToolbox($executed));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => ['client_tool']]);

        $this->assertSame(['server_tool'], $executed);
        $this->assertCount(2, $this->invokedMessages);
        $this->assertSame('Done', $update->getResult()->getContent());
        $this->assertSame(ExecutionStatus::Completed, ExecutionState::statusOf($update->getMessages()));
    }

    public function testToolMessagesOfAPartialDeferFollowTheOrderOfTheModelsCalls()
    {
        $first = new ToolCall('id1', 'server_a', []);
        $deferred = new ToolCall('id2', 'client_tool', []);
        $third = new ToolCall('id3', 'server_b', []);
        $executed = [];
        $runner = $this->createRunner($this->platform(new ToolCallResult([$first, $deferred, $third])), $this->recordingToolbox($executed));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => ['client_tool']]);

        $this->assertSame(['server_a', 'server_b'], $executed);
        $answered = [];
        foreach ($update->getMessages()->getMessages() as $message) {
            if ($message instanceof ToolCallMessage) {
                $text = $message->getContent()[0];
                $this->assertInstanceOf(Text::class, $text);
                $answered[] = [$message->getToolCall()->getId(), $text->getText()];
            }
        }
        $this->assertSame([['id1', 'answer of server_a'], ['id3', 'answer of server_b']], $answered);
    }

    public function testAPartialDeferCollectsTheSourcesOfTheExecutedCalls()
    {
        $server = new ToolCall('id1', 'server_tool', []);
        $client = new ToolCall('id2', 'client_tool', []);
        $source = new Source('Title', 'https://example.com', 'Content');
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);
        $toolbox->method('execute')->willReturn(new ToolResult($server, 'answer', new SourceCollection([$source])));
        $runner = $this->createRunner($this->platform(new ToolCallResult([$server, $client])), $toolbox, includeSources: true);

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => ['client_tool']]);

        $this->assertEquals(new SourceCollection([$source]), $update->getResult()->getMetadata()->get('sources'));
    }

    public function testAPartialDeferFiresTheEventWithTheExecutedResults()
    {
        $server = new ToolCall('id1', 'server_tool', []);
        $client = new ToolCall('id2', 'client_tool', []);
        $executed = [];
        $dispatched = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolCallsExecuted::class, static function (ToolCallsExecuted $event) use (&$dispatched): void {
            $dispatched[] = array_map(static fn (ToolResult $result): string => $result->getToolCall()->getName(), $event->getToolResults());
            $event->setResult(new TextResult('Replaced'));
        });
        $toolbox = $this->recordingToolbox($executed);
        $runner = new Runner($this->platform(new ToolCallResult([$server, $client])), $toolbox, new SequentialToolExecutor($toolbox), eventDispatcher: $dispatcher);
        $last = null;
        foreach ($runner->run('gpt-4', new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => ['client_tool']]) as $update) {
            $last = $update;
        }

        $this->assertSame([['server_tool']], $dispatched);
        $this->assertInstanceOf(ResultUpdate::class, $last);
        $this->assertInstanceOf(ToolCallResult::class, $last->getResult());
        $this->assertEquals([$client], ExecutionState::pendingToolCallsOf($last->getMessages()));
    }

    public function testEveryAssistantMessageCarriesItsRoundNumber()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $executions = 0;
        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Done'));
        $runner = $this->createRunner($platform, $this->countingToolbox($toolCall, $executions));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')));

        $this->assertSame([1, 2], $this->roundsOf($update->getMessages()));
    }

    public function testRoundNumbersContinueInTheNextRun()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $executions = 0;
        $platform = $this->platform(new ToolCallResult([$toolCall]), new TextResult('Done'));
        $runner = $this->createRunner($platform, $this->countingToolbox($toolCall, $executions));

        $first = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['max_rounds' => 1]);
        $second = $this->lastUpdate($runner, $first->getMessages());

        $this->assertSame([1], $this->roundsOf($first->getMessages()));
        $this->assertSame([1, 2], $this->roundsOf($second->getMessages()));
    }

    public function testRoundNumbersContinueAfterDeferredToolCalls()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $executions = 0;
        $platform = $this->platform(new ToolCallResult([$toolCall]), new ToolCallResult([$toolCall]), new TextResult('Done'));
        $runner = $this->createRunner($platform, $this->countingToolbox($toolCall, $executions));

        $first = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => true]);
        $bag = $this->withToolResult($first, $toolCall);
        $second = $this->lastUpdate($runner, $bag, ['defer_tool_calls' => true]);
        $bag = $this->withToolResult($second, $toolCall);
        $third = $this->lastUpdate($runner, $bag);

        $this->assertSame([1], $this->roundsOf($first->getMessages()));
        $this->assertSame([1, 2], $this->roundsOf($second->getMessages()));
        $this->assertSame([1, 2, 3], $this->roundsOf($third->getMessages()));
    }

    public function testRoundNumbersContinueAfterTheHighestRoundOnTheMessages()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $executions = 0;
        $runner = $this->createRunner($this->platform(new ToolCallResult([$toolCall]), new TextResult('Done')), $this->countingToolbox($toolCall, $executions));
        $answer = Message::ofAssistant('Rounds one to four in one message');
        $answer->getMetadata()->add('round', 4);

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi'), $answer, Message::ofUser('More')));

        $this->assertSame([4, 5, 6], $this->roundsOf($update->getMessages()));
    }

    public function testRoundNumbersFallBackToTheAssistantMessagesWithoutRoundMetadata()
    {
        $executions = 0;
        $runner = $this->createRunner($this->platform(new TextResult('Done')), $this->countingToolbox(new ToolCall('id1', 'tool1', []), $executions));

        $update = $this->lastUpdate($runner, new MessageBag(
            Message::ofUser('Hi'),
            Message::ofAssistant('One'),
            Message::ofUser('Again'),
            Message::ofAssistant('Two'),
            Message::ofUser('And again'),
        ));

        $this->assertSame([null, null, 3], $this->roundsOf($update->getMessages()));
    }

    public function testStreamedRoundsCarryTheirRoundNumber()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $executions = 0;
        $toolStream = new StreamResult((static function () use ($toolCall) {
            yield new TextDelta('Let me check');
            yield new ToolCallComplete([$toolCall]);
        })());
        $finalStream = new StreamResult((static function () {
            yield new TextDelta('Done');
        })());
        $runner = $this->createRunner($this->platform($toolStream, $finalStream), $this->countingToolbox($toolCall, $executions));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')));

        $this->assertSame([1, 2], $this->roundsOf($update->getMessages()));
    }

    public function testDeferToolCallsHonorsTheToolsOption()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null)]);
        $runner = $this->createRunner($this->platform(new ToolCallResult([$toolCall])), $toolbox);

        $this->expectException(ToolNotFoundException::class);

        $this->collectUpdates($runner, new MessageBag(), ['defer_tool_calls' => true, 'tools' => []]);
    }

    public function testDeferToolCallsWithoutToolExecutorChangesNothing()
    {
        $toolCall = new ToolCall('id1', 'tool1', []);
        $runner = new Runner($this->platform(new ToolCallResult([$toolCall])));

        $update = $this->lastUpdate($runner, new MessageBag(Message::ofUser('Hi')), ['defer_tool_calls' => true]);

        $this->assertInstanceOf(ToolCallResult::class, $update->getResult());
    }

    public function testStepOptionsDoNotReachThePlatform()
    {
        $captured = [];
        $platform = MockPlatformFactory::createPlatform(static function (mixed $model, mixed $input, array $invocationOptions) use (&$captured): TextResult {
            $captured = $invocationOptions;

            return new TextResult('Done');
        });
        $toolbox = $this->createStub(ToolboxInterface::class);

        $this->drive($this->createRunner($platform, $toolbox), new MessageBag(), ['max_rounds' => 2, 'defer_tool_calls' => false, 'temperature' => 0.5]);

        $this->assertSame(['temperature' => 0.5], $captured);
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('provideInvalidStepOptions')]
    public function testInvalidStepOptionsThrow(array $options)
    {
        $runner = $this->createRunner($this->platform(new TextResult('Done')), $this->createStub(ToolboxInterface::class));

        $this->expectException(InvalidArgumentException::class);

        $this->collectUpdates($runner, new MessageBag(), $options);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidStepOptions(): iterable
    {
        yield 'zero rounds' => [['max_rounds' => 0]];
        yield 'negative rounds' => [['max_rounds' => -1]];
        yield 'string rounds' => [['max_rounds' => '2']];
        yield 'non-bool defer' => [['defer_tool_calls' => 'yes']];
        yield 'defer list with a non-string' => [['defer_tool_calls' => ['tool1', 1]]];
        yield 'defer list with an empty name' => [['defer_tool_calls' => ['']]];
        yield 'defer map' => [['defer_tool_calls' => ['tool1' => true]]];
        yield 'defer callable string' => [['defer_tool_calls' => 'strlen']];
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

    private function withToolResult(ResultUpdate $update, ToolCall $toolCall): MessageBag
    {
        $messages = $update->getMessages();
        $this->assertInstanceOf(MessageBag::class, $messages);

        return $messages->with(Message::ofToolCall($toolCall, 'result'));
    }

    /**
     * @return list<int>
     */
    private function roundsOf(?MessageBag $messages): array
    {
        $this->assertInstanceOf(MessageBag::class, $messages);

        $rounds = [];
        foreach ($messages->getMessages() as $message) {
            if ($message instanceof AssistantMessage) {
                $rounds[] = $message->getMetadata()->get('round');
            }
        }

        return $rounds;
    }

    /**
     * @param list<string> $executed
     *
     * @param-out list<string> $executed
     */
    private function recordingToolbox(array &$executed): ToolboxInterface
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);
        $toolbox->method('execute')->willReturnCallback(static function (ToolCall $toolCall) use (&$executed): ToolResult {
            $executed[] = $toolCall->getName();

            return new ToolResult($toolCall, 'answer of '.$toolCall->getName());
        });

        return $toolbox;
    }

    private function countingToolbox(ToolCall $toolCall, int &$executions): ToolboxInterface
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);
        $toolbox->method('execute')->willReturnCallback(static function () use ($toolCall, &$executions): ToolResult {
            ++$executions;

            return new ToolResult($toolCall, 'Tool answer');
        });

        return $toolbox;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function lastUpdate(Runner $runner, MessageBag $messages, array $options = []): ResultUpdate
    {
        $updates = $this->collectUpdates($runner, $messages, $options);
        $last = end($updates);
        $this->assertInstanceOf(ResultUpdate::class, $last);

        return $last;
    }

    /**
     * @return array{type: 'function', function: array{name: string, description: string}}
     */
    private function functionTool(string $name, string $description): array
    {
        return ['type' => 'function', 'function' => ['name' => $name, 'description' => $description]];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function captureOptions(ToolboxInterface $toolbox, array $options): array
    {
        $captured = [];
        $platform = MockPlatformFactory::createPlatform(static function (mixed $model, mixed $input, array $invocationOptions) use (&$captured): TextResult {
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
        bool $includeSources = false,
    ): Runner {
        return new Runner(
            $platform,
            $toolbox,
            new SequentialToolExecutor($toolbox),
            $maxToolCalls,
            $includeSources,
        );
    }

    private function platform(ResultInterface ...$results): PlatformInterface
    {
        $invocation = 0;

        $platform = MockPlatformFactory::createPlatform(static function () use (&$invocation, $results): ResultInterface {
            if (!isset($results[$invocation])) {
                throw new \LogicException(\sprintf('The platform was invoked %d times, but only %d results are configured.', $invocation + 1, \count($results)));
            }

            return $results[$invocation++];
        });

        $record = function (MessageBag $messages): void {
            $this->invokedMessages[] = $messages;
        };

        return new class($platform, $record) implements PlatformInterface {
            public function __construct(
                private readonly PlatformInterface $platform,
                private readonly \Closure $record,
            ) {
            }

            public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
            {
                if ($input instanceof MessageBag) {
                    ($this->record)($input);
                }

                return $this->platform->invoke($model, $input, $options);
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                return $this->platform->getModelCatalog();
            }
        };
    }

    private function lastInvokedMessages(): MessageBag
    {
        $this->assertNotSame([], $this->invokedMessages);

        return $this->invokedMessages[array_key_last($this->invokedMessages)];
    }
}

final class StreamedRecipe
{
    public string $title;
    public int $minutes;
}

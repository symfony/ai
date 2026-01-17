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
use Symfony\AI\Agent\Toolbox\StreamListener;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\ChunkEvent;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;

final class StreamListenerTest extends TestCase
{
    public function testGetContentWithOnlyStringValues()
    {
        $streamResult = new StreamResult((function (): \Generator {
            yield 'Hello ';
            yield 'World';
        })());

        $callbackCalled = false;
        $handleToolCallsCallback = function () use (&$callbackCalled) {
            $callbackCalled = true;
        };

        $streamResult->addListener(new StreamListener($handleToolCallsCallback));
        $result = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Hello ', 'World'], $result);
        $this->assertFalse($callbackCalled);
    }

    public function testGetContentWithToolCallResultAfterStringValues()
    {
        $toolCallResult = new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        $streamResult = new StreamResult((function () use ($toolCallResult): \Generator {
            yield 'Initial ';
            yield 'content ';
            yield $toolCallResult;
        })());

        $capturedAssistantMessage = null;
        $capturedToolCallResult = null;
        $handleToolCallsCallback = function (ToolCallResult $tcr, AssistantMessage $msg) use (&$capturedAssistantMessage, &$capturedToolCallResult) {
            $capturedToolCallResult = $tcr;
            $capturedAssistantMessage = $msg;

            return new TextResult('Tool response');
        };

        $streamResult->addListener(new StreamListener($handleToolCallsCallback));
        $result = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Initial ', 'content ', 'Tool response'], $result);
        $this->assertNotNull($capturedAssistantMessage);
        $this->assertSame('Initial content ', $capturedAssistantMessage->getContent());
        $this->assertNotNull($capturedToolCallResult);
        $this->assertSame($toolCallResult, $capturedToolCallResult);
    }

    public function testGetContentWithToolCallResultAsFirstValue()
    {
        $streamResult = new StreamResult((function (): \Generator {
            yield new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        })());

        $capturedAssistantMessage = null;
        $handleToolCallsCallback = function (ToolCallResult $tcr, AssistantMessage $msg) use (&$capturedAssistantMessage) {
            $capturedAssistantMessage = $msg;

            return new TextResult('Immediate tool response');
        };

        $streamResult->addListener(new StreamListener($handleToolCallsCallback));
        $result = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Immediate tool response'], $result);
        $this->assertNotNull($capturedAssistantMessage);
        $this->assertSame('', $capturedAssistantMessage->getContent());
    }

    public function testGetContentWithToolCallResultReturningGenerator()
    {
        $streamResult = new StreamResult((function (): \Generator {
            yield 'Start';
            yield new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        })());

        $capturedAssistantMessage = null;
        $handleToolCallsCallback = function (ToolCallResult $tcr, AssistantMessage $msg) use (&$capturedAssistantMessage) {
            $capturedAssistantMessage = $msg;

            return new StreamResult((function (): \Generator {
                yield 'Part 1';
                yield 'Part 2';
                yield 'Part 3';
            })());
        };

        $streamResult->addListener(new StreamListener($handleToolCallsCallback));
        $result = iterator_to_array($streamResult->getContent(), false);

        $this->assertSame(['Start', 'Part 1', 'Part 2', 'Part 3'], $result);
        $this->assertSame('Start', $capturedAssistantMessage->getContent());
    }

    public function testGetContentStopsAfterToolCallResult()
    {
        $streamResult = new StreamResult((function (): \Generator {
            yield 'Before';
            yield new ToolCallResult(new ToolCall('test-id', 'test_tool'));
            yield 'After'; // This should not be yielded
        })());

        $innerResult = new TextResult('Tool output');
        $handleToolCallsCallback = fn () => $innerResult;

        $streamResult->addListener(new StreamListener($handleToolCallsCallback));
        $result = iterator_to_array($streamResult->getContent(), false);

        $this->assertSame(['Before', 'Tool output'], $result);
    }

    public function testMetadataPropagationFromTextResult()
    {
        $streamResult = new StreamResult((function (): \Generator {
            yield 'Before tool';
            yield new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        })());
        $streamResult->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 100, completionTokens: 10, totalTokens: 110));

        $innerResult = new TextResult('Tool response');
        $innerResult->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 200, completionTokens: 20, totalTokens: 220));

        $streamResult->addListener(new StreamListener(fn () => $innerResult));
        iterator_to_array($streamResult->getContent());

        $this->assertTrue($streamResult->getMetadata()->has('token_usage'));
        $this->assertInstanceOf(TokenUsageAggregation::class, $usage = $streamResult->getMetadata()->get('token_usage'));
        $this->assertSame(330, $usage->getTotalTokens());
    }

    public function testMetadataPropagationFromNestedStreamResultWithEagerMetadata()
    {
        $innerTokenUsage = new TokenUsage(promptTokens: 200, completionTokens: 20, totalTokens: 220);

        $streamResult = new StreamResult((function (): \Generator {
            yield 'Before tool';
            yield new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        })());
        $streamResult->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 100, completionTokens: 10, totalTokens: 110));

        $innerResult = new StreamResult((function (): \Generator {
            yield 'Part 1';
            yield 'Part 2';
        })());
        $innerResult->getMetadata()->add('token_usage', $innerTokenUsage);

        $streamResult->addListener(new StreamListener(fn () => $innerResult));
        iterator_to_array($streamResult->getContent());

        $this->assertTrue($streamResult->getMetadata()->has('token_usage'));
        $this->assertInstanceOf(TokenUsageAggregation::class, $usage = $streamResult->getMetadata()->get('token_usage'));
        $this->assertSame(330, $usage->getTotalTokens());
    }

    public function testMetadataPropagationFromNestedStreamResultWithLazyMetadata()
    {
        $streamResult = new StreamResult((function (): \Generator {
            yield 'Before tool';
            yield new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        })());
        $streamResult->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 100, completionTokens: 10, totalTokens: 110));

        $innerResult = new StreamResult((function (): \Generator {
            yield 'Part 1';
            yield 'Part 2';
        })());
        $innerResult->addListener(new class extends AbstractStreamListener {
            public function onChunk(ChunkEvent $event): void
            {
                if ('Part 2' === $event->getChunk()) {
                    $event->getResult()->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 200, completionTokens: 20, totalTokens: 220));
                }
            }
        });

        $streamResult->addListener(new StreamListener(fn () => $innerResult));
        iterator_to_array($streamResult->getContent());

        $this->assertTrue($streamResult->getMetadata()->has('token_usage'));
        $this->assertInstanceOf(TokenUsageAggregation::class, $usage = $streamResult->getMetadata()->get('token_usage'));
        $this->assertSame(330, $usage->getTotalTokens());
    }
}

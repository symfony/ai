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
use Symfony\AI\Agent\Toolbox\StreamResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Result\StreamResult as PlatformStreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

final class StreamResultTest extends TestCase
{
    public function testGetContentWithOnlyStringValues()
    {
        $generator = (function (): \Generator {
            yield 'Hello ';
            yield 'World';
        })();

        $callbackCalled = false;
        $handleToolCallsCallback = function () use (&$callbackCalled) {
            $callbackCalled = true;
        };

        $streamResult = new StreamResult($generator, $handleToolCallsCallback);
        $result = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Hello ', 'World'], $result);
        $this->assertFalse($callbackCalled);
    }

    public function testGetContentWithToolCallResultAfterStringValues()
    {
        $toolCallResult = new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        $generator = (function () use ($toolCallResult): \Generator {
            yield 'Initial ';
            yield 'content ';
            yield $toolCallResult;
        })();

        $capturedAssistantMessage = null;
        $capturedToolCallResult = null;
        $innerResult = new TextResult('Tool response');
        $innerResult->getMetadata()->add('inner_key', 'inner_value');
        $handleToolCallsCallback = function (ToolCallResult $tcr, AssistantMessage $msg) use (&$capturedAssistantMessage, &$capturedToolCallResult, $innerResult) {
            $capturedToolCallResult = $tcr;
            $capturedAssistantMessage = $msg;

            return $innerResult;
        };

        $streamResult = new StreamResult($generator, $handleToolCallsCallback);
        $result = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Initial ', 'content ', 'Tool response'], $result);
        $this->assertNotNull($capturedAssistantMessage);
        $this->assertSame('Initial content ', $capturedAssistantMessage->getContent());
        $this->assertNotNull($capturedToolCallResult);
        $this->assertSame($toolCallResult, $capturedToolCallResult);
        $this->assertSame('inner_value', $streamResult->getMetadata()->get('inner_key'));
    }

    public function testGetContentWithToolCallResultAsFirstValue()
    {
        $generator = (function (): \Generator {
            yield new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        })();

        $capturedAssistantMessage = null;
        $innerResult = new TextResult('Immediate tool response');
        $handleToolCallsCallback = function (ToolCallResult $tcr, AssistantMessage $msg) use (&$capturedAssistantMessage, $innerResult) {
            $capturedAssistantMessage = $msg;

            return $innerResult;
        };

        $streamResult = new StreamResult($generator, $handleToolCallsCallback);
        $result = iterator_to_array($streamResult->getContent());

        $this->assertSame(['Immediate tool response'], $result);
        $this->assertNotNull($capturedAssistantMessage);
        $this->assertSame('', $capturedAssistantMessage->getContent());
    }

    public function testGetContentWithToolCallResultReturningGenerator()
    {
        $generator = (function (): \Generator {
            yield 'Start';
            yield new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        })();

        $capturedAssistantMessage = null;
        $innerResult = new PlatformStreamResult((function (): \Generator {
            yield 'Part 1';
            yield 'Part 2';
            yield 'Part 3';
        })());
        $handleToolCallsCallback = function (ToolCallResult $tcr, AssistantMessage $msg) use (&$capturedAssistantMessage, $innerResult) {
            $capturedAssistantMessage = $msg;

            return $innerResult;
        };

        $streamResult = new StreamResult($generator, $handleToolCallsCallback);
        $result = iterator_to_array($streamResult->getContent(), false);

        $this->assertSame(['Start', 'Part 1', 'Part 2', 'Part 3'], $result);
        $this->assertSame('Start', $capturedAssistantMessage->getContent());
    }

    public function testGetContentStopsAfterToolCallResult()
    {
        $toolCall = new ToolCall('test-id', 'test_tool');
        $toolCallResult = new ToolCallResult($toolCall);

        $generator = (function () use ($toolCallResult): \Generator {
            yield 'Before';
            yield $toolCallResult;
            yield 'After'; // This should not be yielded
        })();

        $innerResult = new TextResult('Tool output');
        $handleToolCallsCallback = fn () => $innerResult;

        $streamResult = new StreamResult($generator, $handleToolCallsCallback);
        $result = iterator_to_array($streamResult->getContent(), false);

        $this->assertSame(['Before', 'Tool output'], $result);
    }

    public function testMetadataPropagation()
    {
        $generator = (function (): \Generator {
            yield new ToolCallResult(new ToolCall('test-id', 'test_tool'));
        })();

        $innerResult = new TextResult('Tool output');
        $innerResult->getMetadata()->add('key1', 'value1');
        $innerResult->getMetadata()->add('key2', 'value2');

        $handleToolCallsCallback = fn () => $innerResult;

        $streamResult = new StreamResult($generator, $handleToolCallsCallback);
        iterator_to_array($streamResult->getContent());

        $this->assertSame('value1', $streamResult->getMetadata()['key1']);
        $this->assertSame('value2', $streamResult->getMetadata()['key2']);
    }
}

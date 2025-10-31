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
use Symfony\AI\Agent\Toolbox\StreamResult as ToolboxStreamResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

final class StreamResultTest extends TestCase
{
    public function testStreamsPlainChunksWithoutToolCall()
    {
        $chunks = ['He', 'llo'];
        $generator = (function () use ($chunks) {
            foreach ($chunks as $c) {
                yield $c;
            }
        })();

        $callbackCalled = false;
        $callback = function () use (&$callbackCalled) {
            $callbackCalled = true;

            // Return any result, won't be used in this test
            return new class extends BaseResult {
                public function getContent(): iterable
                {
                    yield 'ignored';
                }
            };
        };

        $stream = new ToolboxStreamResult($generator, $callback);
        $received = [];
        foreach ($stream->getContent() as $value) {
            $received[] = $value;
        }

        $this->assertSame($chunks, $received);
        $this->assertFalse($callbackCalled, 'Callback should not be called when no ToolCallResult appears.');
    }

    public function testInvokesCallbackOnToolCallAndYieldsItsContent()
    {
        $toolCallResult = new ToolCallResult(new ToolCall('id1', 'tool1', ['arg' => 'value']));

        $generator = (function () use ($toolCallResult) {
            yield 'He';
            yield 'llo';
            yield $toolCallResult;
            yield 'AFTER';
        })();

        $receivedAssistantMessage = null;
        $receivedToolCallResult = null;

        $callback = function (ToolCallResult $result, AssistantMessage $assistantMessage) use (&$receivedAssistantMessage, &$receivedToolCallResult) {
            $receivedToolCallResult = $result;
            $receivedAssistantMessage = $assistantMessage;

            // Return a result that itself yields more chunks
            return new class extends BaseResult {
                public function getContent(): iterable
                {
                    yield ' world';
                    yield '!';
                }
            };
        };

        $stream = new ToolboxStreamResult($generator, $callback);

        $received = [];
        foreach ($stream->getContent() as $value) {
            $received[] = $value;
        }

        $this->assertSame(['He', 'llo', ' world', '!', 'AFTER'], $received);
        $this->assertInstanceOf(ToolCallResult::class, $receivedToolCallResult);
        $this->assertInstanceOf(AssistantMessage::class, $receivedAssistantMessage);
        $this->assertSame('Hello', $receivedAssistantMessage->content);
    }

    public function testStreamsPlainChunksWithTokenUsage()
    {
        $chunks = [
            'He',
            'llo',
            new TokenUsage(),
        ];
        $generator = (function () use ($chunks) {
            foreach ($chunks as $c) {
                yield $c;
            }
        })();

        $callbackCalled = false;
        $callback = function () use (&$callbackCalled) {
            $callbackCalled = true;

            // Return any result, won't be used in this test
            return new class extends BaseResult {
                public function getContent(): iterable
                {
                    yield 'ignored';
                }
            };
        };

        $stream = new ToolboxStreamResult($generator, $callback);
        $received = [];
        foreach ($stream->getContent() as $value) {
            $received[] = $value;
        }

        $this->assertSame($chunks, $received);
        $this->assertFalse($callbackCalled, 'Callback should not be called when no ToolCallResult appears.');
    }
}

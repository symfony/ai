<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode;
use Symfony\AI\Platform\Bridge\ClaudeCode\ResultConverter;
use Symfony\AI\Platform\Bridge\ClaudeCode\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsClaudeCode()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new ClaudeCode('sonnet')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new ResultConverter();

        $this->assertFalse($converter->supports(new Claude('claude-3-5-sonnet-latest')));
    }

    public function testConvertTextResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello, World!',
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, World!', $result->getContent());
    }

    public function testConvertThrowsOnEmptyData()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI did not return any result.');

        $converter->convert($rawResult);
    }

    public function testConvertThrowsOnErrorResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'is_error' => true,
            'result' => 'Something went wrong',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI error: "Something went wrong"');

        $converter->convert($rawResult);
    }

    public function testConvertThrowsOnMissingResultField()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI result does not contain a "result" field.');

        $converter->convert($rawResult);
    }

    public function testConvertStreamingReturnsStreamResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello']]],
                ['type' => 'result', 'result' => 'Hello'],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertSame(['Hello'], $chunks);
    }

    public function testConvertStreamingYieldsTextDeltas()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello, ']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'World!']]],
                ['type' => 'result', 'result' => 'Hello, World!'],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertSame(['Hello, ', 'World!'], $chunks);
    }

    public function testConvertStreamingIgnoresNonTextEvents()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'system', 'subtype' => 'init'],
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello']]],
                ['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'Hello']]]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop']],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => 'Hello'],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertSame(['Hello'], $chunks);
    }

    public function testGetTokenUsageExtractor()
    {
        $converter = new ResultConverter();

        $this->assertInstanceOf(TokenUsageExtractor::class, $converter->getTokenUsageExtractor());
    }
}

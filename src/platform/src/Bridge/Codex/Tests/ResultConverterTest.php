<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Bridge\Codex\ResultConverter;
use Symfony\AI\Platform\Bridge\Codex\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsCodex()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Codex('gpt-5-codex')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new ResultConverter();

        $this->assertFalse($converter->supports(new Model('sonnet')));
    }

    public function testConvertTextResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message', 'text' => 'Hello, World!'],
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
        $this->expectExceptionMessage('Codex CLI did not return any result.');

        $converter->convert($rawResult);
    }

    public function testConvertThrowsOnErrorResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'error',
            'message' => 'Something went wrong',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex CLI error: "Something went wrong"');

        $converter->convert($rawResult);
    }

    public function testConvertThrowsOnMissingTextField()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex CLI result does not contain a text field.');

        $converter->convert($rawResult);
    }

    public function testConvertStreamingReturnsStreamResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Hello']],
                ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
    }

    public function testConvertStreamingYieldsMultipleAgentMessages()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'First part.']],
                ['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'command' => 'ls']],
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Second part.']],
                ['type' => 'turn.completed', 'usage' => ['input_tokens' => 50, 'output_tokens' => 20]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('First part.', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('Second part.', $chunks[1]->getText());
    }

    public function testConvertStreamingIgnoresNonAgentEvents()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'thread.started', 'thread_id' => 'test-123'],
                ['type' => 'turn.started'],
                ['type' => 'item.started', 'item' => ['type' => 'command_execution', 'status' => 'in_progress']],
                ['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'command' => 'ls']],
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Hello']],
                ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
    }

    public function testGetTokenUsageExtractor()
    {
        $converter = new ResultConverter();

        $this->assertInstanceOf(TokenUsageExtractor::class, $converter->getTokenUsageExtractor());
    }
}

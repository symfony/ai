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
use Symfony\AI\Platform\Bridge\ClaudeCode\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TokenUsageExtractorTest extends TestCase
{
    public function testExtractReturnsTokenUsage()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ]);

        $tokenUsage = $extractor->extract($rawResult);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(100, $tokenUsage->getPromptTokens());
        $this->assertSame(50, $tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getCachedTokens());
    }

    public function testExtractWithCachedTokens()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cache_creation_input_tokens' => 9864,
                'cache_read_input_tokens' => 14162,
            ],
        ]);

        $tokenUsage = $extractor->extract($rawResult);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(100, $tokenUsage->getPromptTokens());
        $this->assertSame(50, $tokenUsage->getCompletionTokens());
        $this->assertSame(24026, $tokenUsage->getCachedTokens());
        $this->assertSame(9864, $tokenUsage->getCacheCreationTokens());
        $this->assertSame(14162, $tokenUsage->getCacheReadTokens());
    }

    public function testExtractWithOnlyCacheReadTokens()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cache_read_input_tokens' => 14162,
            ],
        ]);

        $tokenUsage = $extractor->extract($rawResult);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(14162, $tokenUsage->getCachedTokens());
        $this->assertNull($tokenUsage->getCacheCreationTokens());
        $this->assertSame(14162, $tokenUsage->getCacheReadTokens());
    }

    public function testExtractWithOnlyCacheCreationTokens()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cache_creation_input_tokens' => 9864,
            ],
        ]);

        $tokenUsage = $extractor->extract($rawResult);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(9864, $tokenUsage->getCachedTokens());
        $this->assertSame(9864, $tokenUsage->getCacheCreationTokens());
        $this->assertNull($tokenUsage->getCacheReadTokens());
    }

    public function testExtractWithNullCacheTokens()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cache_creation_input_tokens' => null,
                'cache_read_input_tokens' => null,
            ],
        ]);

        $tokenUsage = $extractor->extract($rawResult);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertNull($tokenUsage->getCachedTokens());
        $this->assertNull($tokenUsage->getCacheCreationTokens());
        $this->assertNull($tokenUsage->getCacheReadTokens());
    }

    public function testExtractFromRecordedResult()
    {
        $events = array_map(static fn (string $line): array => json_decode($line, true, flags: \JSON_THROW_ON_ERROR), file(__DIR__.'/Fixtures/read_tool.jsonl'));
        $result = array_values(array_filter($events, static fn (array $event): bool => 'result' === ($event['type'] ?? null)))[0];

        $tokenUsage = (new TokenUsageExtractor())->extract(new InMemoryRawResult($result));

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(4, $tokenUsage->getPromptTokens());
        $this->assertSame(73, $tokenUsage->getCompletionTokens());
        $this->assertSame(9384, $tokenUsage->getCacheCreationTokens());
        $this->assertSame(29387, $tokenUsage->getCacheReadTokens());
        $this->assertSame(38771, $tokenUsage->getCachedTokens());
    }

    public function testExtractReturnsNullForStreaming()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]);

        $this->assertNull($extractor->extract($rawResult, ['stream' => true]));
    }

    public function testExtractReturnsNullWhenNoUsageField()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello',
        ]);

        $this->assertNull($extractor->extract($rawResult));
    }
}

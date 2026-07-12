<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests\Completions;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\Completions\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class TokenUsageExtractorTest extends TestCase
{
    public function testItHandlesStreamResponsesWithoutProcessing()
    {
        $extractor = new TokenUsageExtractor();

        $this->assertNull($extractor->extract(new InMemoryRawResult(), ['stream' => true]));
    }

    public function testItDoesNothingWithoutUsageData()
    {
        $extractor = new TokenUsageExtractor();

        $this->assertNull($extractor->extract(new InMemoryRawResult(['some' => 'data'])));
    }

    public function testItExtractsTokenUsage()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
                'num_cached_tokens' => 5,
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(20, $tokenUsage->getCompletionTokens());
        $this->assertSame(5, $tokenUsage->getCachedTokens());
        $this->assertNull($tokenUsage->getCacheReadTokens());
        $this->assertSame(30, $tokenUsage->getTotalTokens());
    }

    public function testItHandlesMissingUsageFields()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'usage' => [
                'prompt_tokens' => 10,
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertNull($tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getCachedTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
    }

    public function testItExtractsTokenUsageFromUsageArray()
    {
        $extractor = new TokenUsageExtractor();

        $tokenUsage = $extractor->extractFromArray([
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
            'num_cached_tokens' => 5,
        ]);

        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(20, $tokenUsage->getCompletionTokens());
        $this->assertSame(5, $tokenUsage->getCachedTokens());
        $this->assertNull($tokenUsage->getCacheReadTokens());
        $this->assertSame(30, $tokenUsage->getTotalTokens());
    }

    public function testItExtractsPromptTokensDetailsCachedTokens()
    {
        $extractor = new TokenUsageExtractor();

        $tokenUsage = $extractor->extractFromArray([
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'prompt_tokens_details' => [
                'cached_tokens' => 78,
            ],
            'total_tokens' => 150,
        ]);

        $this->assertSame(78, $tokenUsage->getCachedTokens());
        $this->assertSame(78, $tokenUsage->getCacheReadTokens());
        $this->assertNull($tokenUsage->getCacheCreationTokens());
    }

    public function testItExtractsDeepSeekPromptCacheHitTokens()
    {
        $extractor = new TokenUsageExtractor();

        $tokenUsage = $extractor->extractFromArray([
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'prompt_cache_hit_tokens' => 60,
            'total_tokens' => 150,
        ]);

        $this->assertSame(60, $tokenUsage->getCacheReadTokens());
        $this->assertSame(60, $tokenUsage->getCachedTokens());
    }

    public function testItExtractsReasoningTokensFromCompletionTokensDetails()
    {
        $extractor = new TokenUsageExtractor();

        $tokenUsage = $extractor->extractFromArray([
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'completion_tokens_details' => [
                'reasoning_tokens' => 20,
            ],
            'total_tokens' => 150,
        ]);

        $this->assertSame(20, $tokenUsage->getThinkingTokens());
    }

    public function testItExtractsLlamaCppStylePromptTokensDetailsCachedTokens()
    {
        $extractor = new TokenUsageExtractor();

        $tokenUsage = $extractor->extractFromArray([
            'prompt_tokens' => 28,
            'completion_tokens' => 1,
            'total_tokens' => 29,
            'prompt_tokens_details' => [
                'cached_tokens' => 24,
            ],
        ]);

        $this->assertSame(24, $tokenUsage->getCacheReadTokens());
        $this->assertSame(24, $tokenUsage->getCachedTokens());
    }

    public function testItExtractsCachedTokensFieldAsAggregate()
    {
        $extractor = new TokenUsageExtractor();

        $tokenUsage = $extractor->extractFromArray([
            'prompt_tokens' => 50,
            'completion_tokens' => 25,
            'cached_tokens' => 30,
            'total_tokens' => 75,
        ]);

        $this->assertSame(30, $tokenUsage->getCachedTokens());
        $this->assertNull($tokenUsage->getCacheReadTokens());
    }
}

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
use Symfony\AI\Platform\Bridge\Codex\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TokenUsageExtractorTest extends TestCase
{
    public function testExtractReturnsTokenUsage()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message', 'text' => 'Hello'],
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
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message', 'text' => 'Hello'],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cached_input_tokens' => 80,
            ],
        ]);

        $tokenUsage = $extractor->extract($rawResult);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(100, $tokenUsage->getPromptTokens());
        $this->assertSame(50, $tokenUsage->getCompletionTokens());
        $this->assertSame(80, $tokenUsage->getCachedTokens());
    }

    public function testExtractReturnsNullForStreaming()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]);

        $this->assertNull($extractor->extract($rawResult, ['stream' => true]));
    }

    public function testExtractReturnsNullWhenNoUsageField()
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message', 'text' => 'Hello'],
        ]);

        $this->assertNull($extractor->extract($rawResult));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;

class TokenUsageAggregationTest extends TestCase
{
    public function testAggregatesTokenUsageCorrectly()
    {
        $usage1 = new TokenUsage(
            promptTokens: 10,
            completionTokens: 20,
            thinkingTokens: 5,
            toolTokens: 2,
            cachedTokens: 1,
            remainingTokens: 100,
            remainingTokensMinute: 60,
            remainingTokensMonth: 1000,
            totalTokens: 38
        );
        $usage2 = new TokenUsage(
            promptTokens: 5,
            completionTokens: 10,
            thinkingTokens: 3,
            toolTokens: 1,
            cachedTokens: 2,
            remainingTokens: 80,
            remainingTokensMinute: 50,
            remainingTokensMonth: 900,
            totalTokens: 21
        );
        $aggregation = new TokenUsageAggregation($usage1, $usage2);

        $this->assertSame(15, $aggregation->getPromptTokens());
        $this->assertSame(30, $aggregation->getCompletionTokens());
        $this->assertSame(8, $aggregation->getThinkingTokens());
        $this->assertSame(3, $aggregation->getToolTokens());
        $this->assertSame(3, $aggregation->getCachedTokens());
        $this->assertSame(80, $aggregation->getRemainingTokens());
        $this->assertSame(50, $aggregation->getRemainingTokensMinute());
        $this->assertSame(900, $aggregation->getRemainingTokensMonth());
        $this->assertSame(59, $aggregation->getTotalTokens());
    }

    public function testHandlesNullValues()
    {
        $usage1 = new TokenUsage(promptTokens: null, completionTokens: null, remainingTokens: null, totalTokens: null);
        $usage2 = new TokenUsage(promptTokens: 5, completionTokens: 10, remainingTokens: 25, totalTokens: 21);
        $aggregation = new TokenUsageAggregation($usage1, $usage2);

        $this->assertSame(5, $aggregation->getPromptTokens());
        $this->assertSame(10, $aggregation->getCompletionTokens());
        $this->assertSame(25, $aggregation->getRemainingTokens());
        $this->assertSame(21, $aggregation->getTotalTokens());
    }

    public function testHandlesOnlyNullValues()
    {
        $usage1 = new TokenUsage();
        $usage2 = new TokenUsage();
        $aggregation = new TokenUsageAggregation($usage1, $usage2);

        $this->assertNull($aggregation->getPromptTokens());
        $this->assertNull($aggregation->getCompletionTokens());
        $this->assertNull($aggregation->getRemainingTokens());
        $this->assertNull($aggregation->getTotalTokens());
    }
}

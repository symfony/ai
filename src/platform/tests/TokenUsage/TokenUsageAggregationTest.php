<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\TokenUsage;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;

class TokenUsageAggregationTest extends TestCase
{
    public function testGetTokenUsagesReturnsTheAggregatedUsagesInOrder()
    {
        $first = new TokenUsage(10);
        $second = new TokenUsage(7);

        $aggregation = new TokenUsageAggregation([$first, $second]);

        $this->assertSame([$first, $second], $aggregation->getTokenUsages());
    }

    public function testGetTokenUsagesReflectsUsagesAddedAfterConstruction()
    {
        $first = new TokenUsage(10);
        $second = new TokenUsage(7);

        $aggregation = new TokenUsageAggregation([$first]);
        $aggregation->add($second);

        $this->assertSame([$first, $second], $aggregation->getTokenUsages());
    }

    public function testAggregatesTokenUsageCorrectly()
    {
        $usage1 = new TokenUsage(
            10,
            20,
            5,
            2,
            1,
            null,
            null,
            100,
            60,
            1000,
            38
        );
        $usage2 = new TokenUsage(
            5,
            10,
            3,
            1,
            2,
            null,
            null,
            80,
            50,
            900,
            21
        );
        $aggregation = new TokenUsageAggregation([$usage1, $usage2]);

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
        $usage1 = new TokenUsage(null, null, null, null, null, null, null, null, null, null, null);
        $usage2 = new TokenUsage(5, 10, null, null, null, null, null, 25, null, null, 21);
        $aggregation = new TokenUsageAggregation([$usage1, $usage2]);

        $this->assertSame(5, $aggregation->getPromptTokens());
        $this->assertSame(10, $aggregation->getCompletionTokens());
        $this->assertSame(25, $aggregation->getRemainingTokens());
        $this->assertSame(21, $aggregation->getTotalTokens());
    }

    public function testHandlesOnlyNullValues()
    {
        $aggregation = new TokenUsageAggregation([new TokenUsage(), new TokenUsage()]);

        $this->assertNull($aggregation->getPromptTokens());
        $this->assertNull($aggregation->getCompletionTokens());
        $this->assertNull($aggregation->getRemainingTokens());
        $this->assertNull($aggregation->getTotalTokens());
    }

    public function testCountMethod()
    {
        $aggregation = new TokenUsageAggregation([new TokenUsage(), new TokenUsage()]);

        $this->assertSame(2, $aggregation->count());

        $aggregation->add(new TokenUsage());
        $this->assertSame(3, $aggregation->count());

        $aggregation->add(new TokenUsageAggregation([new TokenUsage(), new TokenUsage()]));
        $this->assertSame(5, $aggregation->count());
    }

    public function testReturnsModelWhenAllUsagesHaveSameModel()
    {
        $usage1 = new TokenUsage(10, 20, null, null, null, null, null, null, null, null, null, 'gpt-4o');
        $usage2 = new TokenUsage(5, 10, null, null, null, null, null, null, null, null, null, 'gpt-4o');
        $aggregation = new TokenUsageAggregation([$usage1, $usage2]);

        $this->assertSame('gpt-4o', $aggregation->getModel());
    }

    public function testReturnsNullWhenUsagesHaveDifferentModels()
    {
        $usage1 = new TokenUsage(10, 20, null, null, null, null, null, null, null, null, null, 'gpt-4o');
        $usage2 = new TokenUsage(5, 10, null, null, null, null, null, null, null, null, null, 'claude-3-5-sonnet');
        $aggregation = new TokenUsageAggregation([$usage1, $usage2]);

        $this->assertNull($aggregation->getModel());
    }

    public function testReturnsNullWhenAllModelsAreNull()
    {
        $usage1 = new TokenUsage(10, 20);
        $usage2 = new TokenUsage(5, 10);
        $aggregation = new TokenUsageAggregation([$usage1, $usage2]);

        $this->assertNull($aggregation->getModel());
    }
}

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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

class TokenUsageAggregationTest extends TestCase
{
    public function testGetTokenUsagesReturnsTheAggregatedUsagesInOrder()
    {
        $first = new TokenUsage(promptTokens: 10);
        $second = new TokenUsage(promptTokens: 7);

        $aggregation = new TokenUsageAggregation([$first, $second]);

        $this->assertSame([$first, $second], $aggregation->getTokenUsages());
    }

    public function testGetTokenUsagesReflectsUsagesAddedAfterConstruction()
    {
        $first = new TokenUsage(promptTokens: 10);
        $second = new TokenUsage(promptTokens: 7);

        $aggregation = new TokenUsageAggregation([$first]);
        $aggregation->add($second);

        $this->assertSame([$first, $second], $aggregation->getTokenUsages());
    }

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
        $usage1 = new TokenUsage(promptTokens: null, completionTokens: null, remainingTokens: null, totalTokens: null);
        $usage2 = new TokenUsage(promptTokens: 5, completionTokens: 10, remainingTokens: 25, totalTokens: 21);
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
        $usage1 = new TokenUsage(promptTokens: 10, completionTokens: 20, model: 'gpt-4o');
        $usage2 = new TokenUsage(promptTokens: 5, completionTokens: 10, model: 'gpt-4o');
        $aggregation = new TokenUsageAggregation([$usage1, $usage2]);

        $this->assertSame('gpt-4o', $aggregation->getModel());
    }

    public function testReturnsNullWhenUsagesHaveDifferentModels()
    {
        $usage1 = new TokenUsage(promptTokens: 10, completionTokens: 20, model: 'gpt-4o');
        $usage2 = new TokenUsage(promptTokens: 5, completionTokens: 10, model: 'claude-3-5-sonnet');
        $aggregation = new TokenUsageAggregation([$usage1, $usage2]);

        $this->assertNull($aggregation->getModel());
    }

    public function testReturnsNullWhenAllModelsAreNull()
    {
        $usage1 = new TokenUsage(promptTokens: 10, completionTokens: 20);
        $usage2 = new TokenUsage(promptTokens: 5, completionTokens: 10);
        $aggregation = new TokenUsageAggregation([$usage1, $usage2]);

        $this->assertNull($aggregation->getModel());
    }

    /**
     * @param list<string|null> $models
     */
    #[DataProvider('provideModelNames')]
    public function testPreservesModelNames(array $models, ?string $expected)
    {
        $usages = array_map(static fn (?string $model) => new TokenUsage(model: $model), $models);

        $this->assertSame($expected, (new TokenUsageAggregation($usages))->getModel());
    }

    /**
     * @return iterable<string, array{list<string|null>, string|null}>
     */
    public static function provideModelNames(): iterable
    {
        yield 'no usages' => [[], null];
        yield 'single unknown model' => [[null], null];
        yield 'unknown models mixed with known model' => [[null, 'model', null, 'model'], 'model'];
        yield 'empty model name' => [[null, '', ''], ''];
        yield 'zero model name' => [[null, '0', '0'], '0'];
        yield 'distinct numeric strings' => [['1', '01'], null];
        yield 'empty name and nonempty name' => [['', 'model'], null];
        yield 'case-sensitive model names' => [['Model', 'model'], null];
        yield 'mixed models ending with first model' => [['first', 'second', 'first'], null];
    }

    public function testVisitsAllUsagesInOrderWhenModelsDiffer()
    {
        $calls = [];
        $usages = [];
        foreach (['first', 'second', 'first'] as $index => $model) {
            $usage = $this->createMock(TokenUsageInterface::class);
            $usage->expects($this->once())->method('getModel')->willReturnCallback(static function () use (&$calls, $index, $model): string {
                $calls[] = $index;

                return $model;
            });
            $usages[] = $usage;
        }

        $this->assertNull((new TokenUsageAggregation($usages))->getModel());
        $this->assertSame([0, 1, 2], $calls);
    }

    public function testPropagatesGetterExceptionsAfterModelsDiffer()
    {
        $usage = $this->createMock(TokenUsageInterface::class);
        $usage->expects($this->once())->method('getModel')->willThrowException(new RuntimeException('Cannot read model.'));
        $aggregation = new TokenUsageAggregation([
            new TokenUsage(model: 'first'),
            new TokenUsage(model: 'second'),
            $usage,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read model.');

        $aggregation->getModel();
    }

    public function testIgnoresUnknownModelFromNestedAggregation()
    {
        $aggregation = new TokenUsageAggregation([
            new TokenUsageAggregation([new TokenUsage(model: 'first'), new TokenUsage(model: 'second')]),
            new TokenUsage(model: 'first'),
        ]);

        $this->assertSame('first', $aggregation->getModel());
    }

    /**
     * @param list<mixed> $usages
     */
    #[DataProvider('provideInvalidUsages')]
    public function testRejectsInvalidUsages(array $usages)
    {
        $aggregation = new TokenUsageAggregation($usages);

        $this->expectException(\TypeError::class);

        $aggregation->getModel();
    }

    /**
     * @return iterable<string, array{list<mixed>}>
     */
    public static function provideInvalidUsages(): iterable
    {
        yield 'null usage' => [[null]];
        yield 'object without usage interface' => [[new \stdClass()]];
        yield 'invalid entry after different models' => [[new TokenUsage(model: 'first'), new TokenUsage(model: 'second'), null]];
    }
}

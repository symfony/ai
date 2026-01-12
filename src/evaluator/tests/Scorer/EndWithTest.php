<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Evaluator\Tests\Scorer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Evaluator\Scorer\EndWith;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;

final class EndWithTest extends TestCase
{
    #[DataProvider('provideContext')]
    public function testScore(DeferredResult $deferredResult, float $expectedScore)
    {
        $scorer = new EndWith('bar');

        $score = $scorer->score($deferredResult);

        $this->assertSame($expectedScore, $score);
    }

    public static function provideContext(): \Generator
    {
        yield [
            new DeferredResult(new PlainConverter(new TextResult('foo')), new InMemoryRawResult()),
            0.0,
        ];
        yield [
            new DeferredResult(new PlainConverter(new TextResult('bar')), new InMemoryRawResult()),
            1.0,
        ];
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Evaluator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Evaluator\Evaluator;
use Symfony\AI\Evaluator\Scorer\StartWith;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;

final class EvaluatorTest extends TestCase
{
    public function testEvaluatorCanEvaluateWithoutScorers()
    {
        $evaluator = new Evaluator([]);

        $score = $evaluator->evaluate(new DeferredResult(new PlainConverter(new TextResult('foo')), new InMemoryRawResult()));

        $this->assertSame(0.0, $score);
    }

    public function testEvaluatorCanEvaluateWithoutMatchingScorers()
    {
        $evaluator = new Evaluator([
            new StartWith('foo'),
        ]);

        $score = $evaluator->evaluate(new DeferredResult(new PlainConverter(new TextResult('bar')), new InMemoryRawResult()));

        $this->assertSame(0.0, $score);
    }

    public function testEvaluatorCanEvaluateWithMatchingScorers()
    {
        $evaluator = new Evaluator([
            new StartWith('foo'),
        ]);

        $score = $evaluator->evaluate(new DeferredResult(new PlainConverter(new TextResult('foo')), new InMemoryRawResult()));

        $this->assertSame(1.0, $score);
    }
}

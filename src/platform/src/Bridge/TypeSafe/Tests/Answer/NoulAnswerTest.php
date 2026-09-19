<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests\Answer;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\NoulAnswer;

final class NoulAnswerTest extends TestCase
{
    public function testItIsCreatedFromArray()
    {
        $answer = NoulAnswer::fromArray(['type' => 'noul', 'noul' => 1]);

        $this->assertSame(1.0, $answer->getProbability());
    }

    #[TestWith([0.92, 0.5, true])]
    #[TestWith([0.5, 0.5, true])]
    #[TestWith([0.49, 0.5, false])]
    #[TestWith([0.92, 0.95, false])]
    public function testItComparesProbabilityToThreshold(float $probability, float $threshold, bool $expected)
    {
        $this->assertSame($expected, (new NoulAnswer($probability))->isTrue($threshold));
    }
}

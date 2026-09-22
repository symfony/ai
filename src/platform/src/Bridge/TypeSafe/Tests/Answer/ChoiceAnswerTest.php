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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ChoiceAnswer;

final class ChoiceAnswerTest extends TestCase
{
    public function testItIsCreatedFromArray()
    {
        $answer = ChoiceAnswer::fromArray([
            'type' => 'choice',
            'choice' => 'billing',
            'confidence' => 0.51,
            'probabilities' => ['sales' => 0.0, 'billing' => 0.68, 'technical' => 0.32],
        ]);

        $this->assertSame('billing', $answer->getChoice());
        $this->assertSame(['sales' => 0.0, 'billing' => 0.68, 'technical' => 0.32], $answer->getProbabilities());
        $this->assertSame(0.51, $answer->getConfidence());
    }

    public function testItConvertsIntegerNumbersToFloats()
    {
        $answer = ChoiceAnswer::fromArray([
            'type' => 'choice',
            'choice' => 'billing',
            'confidence' => 1,
            'probabilities' => ['billing' => 1, 'sales' => 0],
        ]);

        $this->assertSame(['billing' => 1.0, 'sales' => 0.0], $answer->getProbabilities());
        $this->assertSame(1.0, $answer->getConfidence());
    }

    public function testItExposesTheValuesItIsConstructedWith()
    {
        $answer = new ChoiceAnswer('technical', ['billing' => 0.15, 'technical' => 0.85], 0.82);

        $this->assertSame('technical', $answer->getChoice());
        $this->assertSame(['billing' => 0.15, 'technical' => 0.85], $answer->getProbabilities());
        $this->assertSame(0.82, $answer->getConfidence());
    }
}

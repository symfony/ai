<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Decision\Answer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Decision\Answer\ChoiceAnswer;

final class ChoiceAnswerTest extends TestCase
{
    public function testItExposesTheValuesItIsConstructedWith()
    {
        $answer = new ChoiceAnswer('technical', ['billing' => 0.15, 'technical' => 0.85], 0.82);

        $this->assertSame('technical', $answer->getChoice());
        $this->assertSame(['billing' => 0.15, 'technical' => 0.85], $answer->getProbabilities());
        $this->assertSame(0.82, $answer->getConfidence());
    }
}

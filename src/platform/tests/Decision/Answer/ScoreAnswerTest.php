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
use Symfony\AI\Platform\Decision\Answer\ScoreAnswer;

final class ScoreAnswerTest extends TestCase
{
    public function testItExposesTheValuesItIsConstructedWith()
    {
        $answer = new ScoreAnswer(1.6, ['Calm', 'Frustrated', 'Very angry'], [0.05, 0.3, 0.65], 0.78);

        $this->assertSame(1.6, $answer->getScore());
        $this->assertSame(['Calm', 'Frustrated', 'Very angry'], $answer->getLegend());
        $this->assertSame([0.05, 0.3, 0.65], $answer->getProbabilities());
        $this->assertSame(0.78, $answer->getConfidence());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\HuggingFace\Output;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\Classification;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class ClassificationTest extends TestCase
{
    #[TestDox('Construction with label and score creates valid instance')]
    public function testConstruction()
    {
        $classification = new Classification('positive', 0.95);

        $this->assertSame('positive', $classification->label);
        $this->assertSame(0.95, $classification->score);
    }

    #[TestDox('Constructor accepts various label and score combinations')]
    #[TestWith(['positive', 0.99])]
    #[TestWith(['negative', 0.01])]
    #[TestWith(['neutral', 0.5])]
    #[TestWith(['', 0.5])]
    #[TestWith(['émoji 🎉', 0.8])]
    #[TestWith(['special-chars_123!@#', 0.65])]
    #[TestWith(['minimum', 0.0])]
    #[TestWith(['maximum', 1.0])]
    #[TestWith(['precision', 0.123456789])]
    #[TestWith(['negative_score', -0.5])]
    #[TestWith(['above_one', 1.5])]
    public function testConstructorWithDifferentValues(string $label, float $score)
    {
        $classification = new Classification($label, $score);

        $this->assertSame($label, $classification->label);
        $this->assertSame($score, $classification->score);
    }
}

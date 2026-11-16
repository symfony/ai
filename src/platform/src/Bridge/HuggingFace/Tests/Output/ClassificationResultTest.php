<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\Output;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ClassificationResult;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class ClassificationResultTest extends TestCase
{
    #[TestDox('Construction with classifications array creates valid instance')]
    public function testConstruction()
    {
        $classifications = [
            new Classification('positive', 0.9),
            new Classification('negative', 0.1),
        ];

        $result = new ClassificationResult($classifications);

        $this->assertSame($classifications, $result->getClassifications());
        $this->assertCount(2, $result->getClassifications());
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new ClassificationResult([]);

        $this->assertSame([], $result->getClassifications());
        $this->assertCount(0, $result->getClassifications());
    }
}

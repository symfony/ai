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
use Symfony\AI\Platform\Bridge\HuggingFace\Output\FillMaskResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\MaskFill;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class FillMaskResultTest extends TestCase
{
    #[TestDox('Construction with fills array creates valid instance')]
    public function testConstruction()
    {
        $fills = [
            new MaskFill(100, 'happy', 'I am happy', 0.9),
            new MaskFill(200, 'sad', 'I am sad', 0.05),
            new MaskFill(300, 'excited', 'I am excited', 0.05),
        ];

        $result = new FillMaskResult($fills);

        $this->assertSame($fills, $result->getFills());
        $this->assertCount(3, $result->getFills());
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new FillMaskResult([]);

        $this->assertSame([], $result->getFills());
        $this->assertCount(0, $result->getFills());
    }
}

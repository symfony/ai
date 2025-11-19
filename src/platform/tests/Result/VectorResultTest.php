<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class VectorResultTest extends TestCase
{
    public function testGetContentWithSingleVector()
    {
        $vector = new Vector([0.1, 0.2, 0.3]);
        $result = new VectorResult($vector);

        $this->assertSame([$vector], $result->getContent());
    }

    public function testGetContentWithMultipleVectors()
    {
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $vector3 = new Vector([0.7, 0.8, 0.9]);

        $result = new VectorResult($vector1, $vector2, $vector3);

        $expected = [$vector1, $vector2, $vector3];
        $this->assertSame($expected, $result->getContent());
    }

    public function testGetContentWithNoVectors()
    {
        $result = new VectorResult();

        $this->assertSame([], $result->getContent());
    }

    public function testConstructorAcceptsVariadicVectors()
    {
        $vectors = [
            new Vector([0.1, 0.2]),
            new Vector([0.3, 0.4]),
            new Vector([0.5, 0.6]),
        ];

        $result = new VectorResult(...$vectors);

        $this->assertSame($vectors, $result->getContent());
    }
}

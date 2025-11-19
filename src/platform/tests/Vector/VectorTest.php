<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;

final class VectorTest extends TestCase
{
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(
            VectorInterface::class,
            new Vector([1.0, 2.0, 3.0])
        );
    }

    public function testWithDimensionNull()
    {
        $vector = new Vector($vectors = [1.0, 2.0, 3.0], null);

        $this->assertSame($vectors, $vector->getData());
        $this->assertSame(3, $vector->getDimensions());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;

#[CoversClass(Vector::class)]
final class VectorTest extends TestCase
{
    #[Test]
    public function implementsInterface(): void
    {
        self::assertInstanceOf(
            VectorInterface::class,
            new Vector([1.0, 2.0, 3.0])
        );
    }

    #[Test]
    public function withDimensionNull(): void
    {
        $vector = new Vector($vectors = [1.0, 2.0, 3.0], null);

        self::assertSame($vectors, $vector->getData());
        self::assertSame(3, $vector->getDimensions());
    }
}

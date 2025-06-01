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
use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Exception\RuntimeException;

#[CoversClass(NullVector::class)]
final class NullVectorTest extends TestCase
{
    #[Test]
    public function implementsInterface(): void
    {
        self::assertInstanceOf(VectorInterface::class, new NullVector());
    }

    #[Test]
    public function getDataThrowsOnAccess(): void
    {
        self::expectException(RuntimeException::class);

        (new NullVector())->getData();
    }

    #[Test]
    public function getDimensionsThrowsOnAccess(): void
    {
        self::expectException(RuntimeException::class);

        (new NullVector())->getDimensions();
    }
}

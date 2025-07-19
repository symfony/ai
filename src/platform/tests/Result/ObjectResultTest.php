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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ObjectResult;

#[CoversClass(ObjectResult::class)]
#[Small]
final class ObjectResultTest extends TestCase
{
    #[Test]
    public function getContentWithArray(): void
    {
        $result = new ObjectResult($expected = ['foo' => 'bar', 'baz' => ['qux']]);
        self::assertSame($expected, $result->getContent());
    }

    #[Test]
    public function getContentWithObject(): void
    {
        $result = new ObjectResult($expected = (object) ['foo' => 'bar', 'baz' => ['qux']]);
        self::assertSame($expected, $result->getContent());
    }
}

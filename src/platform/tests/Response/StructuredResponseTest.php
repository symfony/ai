<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Response\ObjectResponse;

#[CoversClass(ObjectResponse::class)]
#[Small]
final class StructuredResponseTest extends TestCase
{
    #[Test]
    public function getContentWithArray(): void
    {
        $response = new ObjectResponse($expected = ['foo' => 'bar', 'baz' => ['qux']]);
        self::assertSame($expected, $response->getContent());
    }

    #[Test]
    public function getContentWithObject(): void
    {
        $response = new ObjectResponse($expected = (object) ['foo' => 'bar', 'baz' => ['qux']]);
        self::assertSame($expected, $response->getContent());
    }
}

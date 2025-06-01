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
use Symfony\AI\Platform\Response\TextResponse;

#[CoversClass(TextResponse::class)]
#[Small]
final class TextResponseTest extends TestCase
{
    #[Test]
    public function getContent(): void
    {
        $response = new TextResponse($expected = 'foo');
        self::assertSame($expected, $response->getContent());
    }
}

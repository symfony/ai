<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAI\DallE;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAI\DallE\Base64Image;

#[CoversClass(Base64Image::class)]
#[Small]
final class Base64ImageTest extends TestCase
{
    #[Test]
    public function itCreatesBase64Image(): void
    {
        $emptyPixel = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $base64Image = new Base64Image($emptyPixel);

        self::assertSame($emptyPixel, $base64Image->encodedImage);
    }

    #[Test]
    public function itThrowsExceptionWhenBase64ImageIsEmpty(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The base64 encoded image generated must be given.');

        new Base64Image('');
    }
}

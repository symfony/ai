<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\DallE;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\Base64Image;

final class Base64ImageTest extends TestCase
{
    public function testItCreatesBase64Image()
    {
        $emptyPixel = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $base64Image = new Base64Image($emptyPixel);

        $this->assertSame($emptyPixel, $base64Image->encodedImage);
    }

    public function testItThrowsExceptionWhenBase64ImageIsEmpty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The base64 encoded image generated must be given.');

        new Base64Image('');
    }
}

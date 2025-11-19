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
use Symfony\AI\Platform\Bridge\OpenAi\DallE\UrlImage;

final class UrlImageTest extends TestCase
{
    public function testItCreatesUrlImage()
    {
        $urlImage = new UrlImage('https://example.com/image.jpg');

        $this->assertSame('https://example.com/image.jpg', $urlImage->url);
    }

    public function testItThrowsExceptionWhenUrlIsEmpty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The image url must be given.');

        new UrlImage('');
    }
}

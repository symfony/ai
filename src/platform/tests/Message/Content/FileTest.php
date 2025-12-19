<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message\Content;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Content\File;

final class FileTest extends TestCase
{
    public function testConstructWithValidDataUrl()
    {
        $image = File::fromDataUrl('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABKklEQVR42mNk+A8AAwMhIv9n+X');

        $this->assertStringStartsWith('data:image/png;base64', $image->asDataUrl());
    }

    public function testWithValidFile()
    {
        $image = File::fromFile(\dirname(__DIR__, 5).'/fixtures/image.jpg');

        $this->assertStringStartsWith('data:image/jpeg;base64,', $image->asDataUrl());
    }

    public function testFromBinaryWithInvalidFile()
    {
        $this->expectExceptionMessage('The file "foo.jpg" does not exist or is not readable.');

        File::fromFile('foo.jpg');
    }

    public function testCanBeSerialized()
    {
        $audio = File::fromFile(\dirname(__DIR__, 5).'/fixtures/image.jpg');

        $serialized = serialize($audio);
        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(File::class, $unserialized);
        $this->assertSame('image.jpg', $unserialized->getFilename());
    }
}

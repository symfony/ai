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
use Symfony\AI\Platform\Message\Content\Audio;

final class AudioTest extends TestCase
{
    public function testConstructWithValidData()
    {
        $audio = new Audio('somedata', 'audio/mpeg');

        $this->assertSame('somedata', $audio->asBinary());
        $this->assertSame('audio/mpeg', $audio->getFormat());
    }

    public function testFromDataUrlWithValidUrl()
    {
        $dataUrl = 'data:audio/mpeg;base64,SUQzBAAAAAAAfVREUkMAAAAMAAADMg==';
        $audio = Audio::fromDataUrl($dataUrl);

        $this->assertSame('SUQzBAAAAAAAfVREUkMAAAAMAAADMg==', $audio->asBase64());
        $this->assertSame('audio/mpeg', $audio->getFormat());
    }

    public function testFromDataUrlWithInvalidUrl()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid audio data URL format.');

        Audio::fromDataUrl('invalid-url');
    }

    public function testFromFileWithValidPath()
    {
        $audio = Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3');

        $this->assertSame('audio/mpeg', $audio->getFormat());
        $this->assertNotEmpty($audio->asBinary());
    }

    public function testFromFileWithInvalidPath()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The file "foo.mp3" does not exist or is not readable.');

        Audio::fromFile('foo.mp3');
    }
}

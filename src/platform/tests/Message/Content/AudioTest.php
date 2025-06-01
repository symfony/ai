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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Content\Audio;

#[CoversClass(Audio::class)]
#[Small]
final class AudioTest extends TestCase
{
    #[Test]
    public function constructWithValidData(): void
    {
        $audio = new Audio('somedata', 'audio/mpeg');

        self::assertSame('somedata', $audio->asBinary());
        self::assertSame('audio/mpeg', $audio->getFormat());
    }

    #[Test]
    public function fromDataUrlWithValidUrl(): void
    {
        $dataUrl = 'data:audio/mpeg;base64,SUQzBAAAAAAAfVREUkMAAAAMAAADMg==';
        $audio = Audio::fromDataUrl($dataUrl);

        self::assertSame('SUQzBAAAAAAAfVREUkMAAAAMAAADMg==', $audio->asBase64());
        self::assertSame('audio/mpeg', $audio->getFormat());
    }

    #[Test]
    public function fromDataUrlWithInvalidUrl(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid audio data URL format.');

        Audio::fromDataUrl('invalid-url');
    }

    #[Test]
    public function fromFileWithValidPath(): void
    {
        $audio = Audio::fromFile(\dirname(__DIR__, 3).'/Fixture/audio.mp3');

        self::assertSame('audio/mpeg', $audio->getFormat());
        self::assertNotEmpty($audio->asBinary());
    }

    #[Test]
    public function fromFileWithInvalidPath(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The file "foo.mp3" does not exist or is not readable.');

        Audio::fromFile('foo.mp3');
    }
}

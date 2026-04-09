<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\DeepgramPayload;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class DeepgramPayloadTest extends TestCase
{
    public function testTextToSpeechAcceptsRawString()
    {
        $payload = new DeepgramPayload('Hello world');

        $this->assertSame('Hello world', $payload->asTextToSpeechPayload());
    }

    public function testTextToSpeechAcceptsNormalizedArray()
    {
        $payload = new DeepgramPayload(['type' => 'text', 'text' => 'Hello world']);

        $this->assertSame('Hello world', $payload->asTextToSpeechPayload());
    }

    public function testTextToSpeechRejectsArrayWithoutTextKey()
    {
        $payload = new DeepgramPayload(['type' => 'text']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The text-to-speech payload must contain a "text" key.');

        $payload->asTextToSpeechPayload();
    }

    public function testTextToSpeechRejectsNonStringText()
    {
        $payload = new DeepgramPayload(['type' => 'text', 'text' => ['Hello']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "text" key of the text-to-speech payload must be a string.');

        $payload->asTextToSpeechPayload();
    }

    public function testGetAudioBinaryDecodesBase64()
    {
        $bytes = "\x00\x01\x02RIFF";
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => [
                'data' => base64_encode($bytes),
                'format' => 'wav',
            ],
        ]);

        $this->assertSame($bytes, $payload->getAudioBinary());
    }

    public function testGetAudioBinaryRejectsMissingInputAudio()
    {
        $payload = new DeepgramPayload(['type' => 'input_audio']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text payload must contain an "input_audio" entry.');

        $payload->getAudioBinary();
    }

    public function testGetAudioBinaryRejectsMissingDataKey()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['format' => 'mp3'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text payload must contain an "input_audio.data" base64-encoded string.');

        $payload->getAudioBinary();
    }

    public function testGetAudioBinaryRejectsInvalidBase64()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['data' => '!!!not-base64!!!', 'format' => 'mp3'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "input_audio.data" entry must be a valid base64-encoded string.');

        $payload->getAudioBinary();
    }

    public function testGetAudioBinaryRejectsRawString()
    {
        $payload = new DeepgramPayload('plain text');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text payload must be a normalized audio array, raw string given.');

        $payload->getAudioBinary();
    }

    #[DataProvider('provideMimeTypeMappings')]
    public function testGetAudioMimeTypeMapping(string $format, string $expected)
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['data' => base64_encode('x'), 'format' => $format],
        ]);

        $this->assertSame($expected, $payload->getAudioMimeType());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMimeTypeMappings(): iterable
    {
        yield 'mp3' => ['mp3', 'audio/mpeg'];
        yield 'wav' => ['wav', 'audio/wav'];
        yield 'ogg' => ['ogg', 'audio/ogg'];
        yield 'flac' => ['flac', 'audio/flac'];
        yield 'webm' => ['webm', 'audio/webm'];
        yield 'aac' => ['aac', 'audio/aac'];
        yield 'mulaw' => ['mulaw', 'audio/x-mulaw'];
        yield 'unknown passthrough' => ['audio/x-custom', 'audio/x-custom'];
    }

    public function testIsUrlBased()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => 'https://example.com/audio.mp3'],
        ]);

        $this->assertTrue($payload->isUrlBased());
        $this->assertSame('https://example.com/audio.mp3', $payload->getAudioUrl());
    }

    public function testIsUrlBasedReturnsFalseForBinaryPayload()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['data' => base64_encode('x'), 'format' => 'mp3'],
        ]);

        $this->assertFalse($payload->isUrlBased());
    }

    public function testGetAudioUrlRejectsNonHttpScheme()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => 'file:///etc/passwd'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text URL must use "http" or "https" scheme, "file" given.');

        $payload->getAudioUrl();
    }

    public function testGetAudioUrlRejectsDataUrls()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => 'data:mp3;base64,AAAA'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text URL must use "http" or "https" scheme, "data" given.');

        $payload->getAudioUrl();
    }

    public function testGetAudioUrlRejectsEmptyString()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => ''],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "url" entry of the speech-to-text payload must be a non-empty string.');

        $payload->getAudioUrl();
    }
}

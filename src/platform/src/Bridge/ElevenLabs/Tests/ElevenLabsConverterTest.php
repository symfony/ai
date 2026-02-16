<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsResultConverter;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ElevenLabsConverterTest extends TestCase
{
    public function testSupportsModel()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());

        $this->assertTrue($converter->supports(new ElevenLabs('eleven_multilingual_v2')));
        $this->assertFalse($converter->supports(new Model('any-model')));
    }

    public function testConvertSpeechToTextResponse()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello there',
        ], [], new class {
            public function getInfo(): string
            {
                return 'speech-to-text';
            }
        });

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello there', $result->getContent());
    }

    public function testConvertTextToSpeechAsStreamResponse()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient([], 'https://api.elevenlabs.io/v1/text-to-speech/JBFqnCBsd6RMkjVDRZzb/stream'));
        $rawResult = new InMemoryRawResult([], [], MockResponse::fromFile(\dirname(__DIR__).'/Tests/Fixtures/audio.mp3', [
            'url' => 'https://api.elevenlabs.io/v1/text-to-speech/JBFqnCBsd6RMkjVDRZzb/stream',
        ]));

        $result = $converter->convert($rawResult, [
            'stream' => true,
        ]);

        $this->assertInstanceOf(StreamResult::class, $result);
    }

    public function testConvertTextToSpeechResponse()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());
        $rawResult = new InMemoryRawResult([], [], new class {
            public function getInfo(): string
            {
                return 'text-to-speech';
            }

            public function getContent(): string
            {
                return file_get_contents(\dirname(__DIR__, 6).'/fixtures/audio.mp3');
            }
        });

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }
}

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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
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
            public function getStatusCode(): int
            {
                return 200;
            }

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
            public function getStatusCode(): int
            {
                return 200;
            }

            public function getInfo(): string
            {
                return 'text-to-speech';
            }

            public function getContent(): string
            {
                $content = file_get_contents(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

                if (!$content) {
                    throw new RuntimeException('Failed to load audio file for text-to-speech response.');
                }

                return $content;
            }
        });

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testConvertThrowsExceptionWithDetailedErrorMessage()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'detail' => [
                    'type' => 'payment_required',
                    'code' => 'paid_plan_required',
                    'message' => 'Free users cannot use library voices via the API. Please upgrade your subscription to use this voice.',
                    'status' => 'payment_required',
                    'request_id' => 'd79eff6fb3690c29ed6883da9fce3159',
                ],
            ], ['http_code' => 402]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Free users cannot use library voices via the API. Please upgrade your subscription to use this voice.');
        $this->expectExceptionCode(0);
        $converter->convert($rawResult);
    }

    public function testCancelDuringStreamIterationStopsCleanly()
    {
        $chunks = ['chunk-1', 'chunk-2', 'chunk-3', 'chunk-4', 'chunk-5'];
        $response = new MockResponse((static function () use ($chunks) {
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        })(), [
            'url' => 'https://api.elevenlabs.io/v1/text-to-speech/JBFqnCBsd6RMkjVDRZzb/stream',
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient([$response]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech/JBFqnCBsd6RMkjVDRZzb/stream'));

        $converter = new ElevenLabsResultConverter($httpClient);
        $result = $converter->convert($rawResult, ['stream' => true]);
        $result->setRawResult($rawResult);

        $this->assertInstanceOf(StreamResult::class, $result);

        $collected = [];
        foreach ($result->getContent() as $delta) {
            $this->assertInstanceOf(BinaryDelta::class, $delta);
            $collected[] = $delta;
            if (2 === \count($collected)) {
                $result->cancel();
            }
        }

        $this->assertLessThan(\count($chunks), \count($collected));
        $this->assertTrue($result->isCancelled());
        $this->assertTrue($rawResult->getObject()->getInfo('canceled'));
    }

    public function testConvertThrowsExceptionWithoutErrorMessage()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());
        $httpClient = new MockHttpClient([
            new MockResponse(
                '',
                ['http_code' => 500]
            ),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The ElevenLabs API returned a non-successful status code "500".');
        $this->expectExceptionCode(0);
        $converter->convert($rawResult);
    }
}

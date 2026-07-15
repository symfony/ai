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
use Symfony\AI\Platform\Bridge\ElevenLabs\Result\AdditionalFormat;
use Symfony\AI\Platform\Bridge\ElevenLabs\Result\Transcript;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
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

    public function testConvertSpeechToTextResponseExposesAdditionalFormats()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello there',
            'additional_formats' => [
                [
                    'requested_format' => 'srt',
                    'file_extension' => '.srt',
                    'content_type' => 'text/plain',
                    'is_base64_encoded' => false,
                    'content' => "1\n00:00:00,000 --> 00:00:01,000\nHello there\n",
                ],
                [
                    'requested_format' => 'txt',
                    'file_extension' => '.txt',
                    'content_type' => 'text/plain',
                    'is_base64_encoded' => true,
                    'content' => base64_encode('Hello there'),
                ],
            ],
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

        $result = $converter->convert($rawResult, [
            'additional_formats' => [
                ['format' => 'srt', 'include_timestamps' => true],
            ],
        ]);

        $this->assertInstanceOf(ObjectResult::class, $result);

        $transcript = $this->extractTranscript($result);
        $this->assertSame('Hello there', $transcript->getText());
        $this->assertSame(
            "1\n00:00:00,000 --> 00:00:01,000\nHello there\n",
            $transcript->asSubRipText(),
        );
        $this->assertSame(
            "1\n00:00:00,000 --> 00:00:01,000\nHello there\n",
            $transcript->getAdditionalFormat('srt'),
        );
        $this->assertSame('Hello there', $transcript->getAdditionalFormat('txt'));
        $this->assertNull($transcript->getAdditionalFormat('html'));

        $additionalFormats = $transcript->getAdditionalFormats();
        $this->assertContainsOnlyInstancesOf(AdditionalFormat::class, $additionalFormats);
        $this->assertCount(2, $additionalFormats);

        $srt = $additionalFormats[0];
        $this->assertSame('srt', $srt->getRequestedFormat());
        $this->assertSame('.srt', $srt->getFileExtension());
        $this->assertSame('text/plain', $srt->getContentType());
        $this->assertFalse($srt->isBase64Encoded());
        $this->assertSame("1\n00:00:00,000 --> 00:00:01,000\nHello there\n", $srt->getDecodedContent());

        $txt = $additionalFormats[1];
        $this->assertTrue($txt->isBase64Encoded());
        $this->assertSame('Hello there', $txt->getDecodedContent());
    }

    public function testGetDecodedContentThrowsRuntimeExceptionOnInvalidBase64Payload()
    {
        $format = AdditionalFormat::fromArray([
            'requested_format' => 'srt',
            'file_extension' => '.srt',
            'content_type' => 'text/plain',
            'is_base64_encoded' => true,
            'content' => 'this-is-not-valid-base64!!!',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The base64-encoded content of the "srt" additional format could not be decoded.');
        $format->getDecodedContent();
    }

    public function testConvertSpeechToTextResponseWithoutAdditionalFormatsOptionReturnsTextResult()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello there',
            // The API happens to return additional formats, but none were requested.
            'additional_formats' => [
                [
                    'requested_format' => 'srt',
                    'file_extension' => '.srt',
                    'content_type' => 'text/plain',
                    'is_base64_encoded' => false,
                    'content' => "1\n00:00:00,000 --> 00:00:01,000\nHello there\n",
                ],
            ],
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

    private function extractTranscript(ResultInterface $result): Transcript
    {
        \assert($result instanceof ObjectResult);
        \assert($result->getContent() instanceof Transcript);

        return $result->getContent();
    }
}

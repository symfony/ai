<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\TextToSpeech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Bridge\Mistral\TextToSpeech;
use Symfony\AI\Platform\Bridge\Mistral\TextToSpeech\Format;
use Symfony\AI\Platform\Bridge\Mistral\TextToSpeech\ResultConverter;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsTextToSpeechModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new TextToSpeech('voxtral-mini-tts-3-0')));
        $this->assertFalse($converter->supports(new SpeechToText('voxtral-mini-latest')));
    }

    public function testItDecodesTheAudioData()
    {
        $result = (new ResultConverter())->convert($this->rawResult(new JsonMockResponse(['audio_data' => base64_encode('mp3-bytes')])));

        $this->assertSame('mp3-bytes', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testItDerivesTheMimeTypeFromTheResponseFormat()
    {
        $result = (new ResultConverter())->convert(
            $this->rawResult(new JsonMockResponse(['audio_data' => base64_encode('wav-bytes')])),
            ['response_format' => Format::WAV],
        );

        $this->assertSame('audio/wav', $result->getMimeType());
    }

    public function testItLeavesTheMimeTypeUnsetForRawFormats()
    {
        $result = (new ResultConverter())->convert(
            $this->rawResult(new JsonMockResponse(['audio_data' => base64_encode('pcm-bytes')])),
            ['response_format' => Format::PCM],
        );

        $this->assertNull($result->getMimeType());
    }

    public function testItThrowsWhenAudioDataIsMissing()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain audio data.');

        (new ResultConverter())->convert($this->rawResult(new JsonMockResponse(['foo' => 'bar'])));
    }

    public function testItThrowsOnBadRequest()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Either ref_audio or voice must be provided.');

        (new ResultConverter())->convert($this->rawResult(new JsonMockResponse(
            ['message' => 'Either ref_audio or voice must be provided.'],
            ['http_code' => 400],
        )));
    }

    private function rawResult(JsonMockResponse $response): RawHttpResult
    {
        return new RawHttpResult((new MockHttpClient($response))->request('POST', 'https://api.mistral.ai/v1/audio/speech'));
    }
}

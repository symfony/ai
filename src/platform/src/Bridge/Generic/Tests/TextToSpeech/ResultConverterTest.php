<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests\TextToSpeech;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\TextToSpeech\ResultConverter;
use Symfony\AI\Platform\Bridge\Generic\TextToSpeechModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsTextToSpeechModelsOnly()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new TextToSpeechModel('kokoro')));
        $this->assertFalse($converter->supports(new CompletionsModel('gpt-4o-mini')));
    }

    #[TestWith(['audio/mpeg', 'audio/mpeg'])]
    #[TestWith(['audio/wav', 'audio/wav'])]
    #[TestWith(['Audio/OGG; codecs=opus', 'audio/ogg'])]
    public function testItConvertsTheAudioBodyToABinaryResult(string $contentType, string $expectedMimeType)
    {
        $result = (new ResultConverter())->convert($this->createRawResult(new MockResponse('audio-bytes', [
            'response_headers' => ['content-type' => $contentType],
        ])));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-bytes', $result->getContent());
        $this->assertSame($expectedMimeType, $result->getMimeType());
    }

    public function testItLeavesTheMimeTypeEmptyWithoutContentType()
    {
        $result = (new ResultConverter())->convert($this->createRawResult(new MockResponse('audio-bytes')));

        $this->assertSame('audio-bytes', $result->getContent());
        $this->assertNull($result->getMimeType());
    }

    public function testItRejectsANonAudioSuccessResponse()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected an audio response, got "application/json"');

        (new ResultConverter())->convert($this->createRawResult(new MockResponse('{"audio_data":"..."}', [
            'response_headers' => ['content-type' => 'application/json'],
        ])));
    }

    public function testItThrowsAuthenticationException()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        (new ResultConverter())->convert($this->createRawResult(new MockResponse('{"error":{"message":"Invalid API key"}}', [
            'http_code' => 401,
            'response_headers' => ['content-type' => 'application/json'],
        ])));
    }

    public function testItThrowsBadRequestException()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown voice "nobody"');

        (new ResultConverter())->convert($this->createRawResult(new MockResponse('{"error":{"message":"Unknown voice \"nobody\""}}', [
            'http_code' => 400,
            'response_headers' => ['content-type' => 'application/json'],
        ])));
    }

    public function testItThrowsServerException()
    {
        $this->expectException(ServerException::class);

        (new ResultConverter())->convert($this->createRawResult(new MockResponse('{"error":{"message":"Engine is loading"}}', [
            'http_code' => 503,
            'response_headers' => ['content-type' => 'application/json'],
        ])));
    }

    public function testItHasNoTokenUsageExtractor()
    {
        $this->assertNull((new ResultConverter())->getTokenUsageExtractor());
    }

    private function createRawResult(MockResponse $response): RawHttpResult
    {
        return new RawHttpResult((new MockHttpClient($response))->request('POST', 'http://localhost:3900/v1/audio/speech'));
    }
}

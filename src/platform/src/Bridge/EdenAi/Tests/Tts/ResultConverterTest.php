<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\Tts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Bridge\EdenAi\Tts\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsTtsModelOnly()
    {
        $converter = new ResultConverter(new MockHttpClient());

        $this->assertTrue($converter->supports(new Tts('audio/tts/amazon/neural')));
        $this->assertFalse($converter->supports(new Ocr('ocr/ocr/google')));
    }

    public function testItDownloadsAudioResource()
    {
        $downloadClient = new MockHttpClient(static function (string $method, string $url): MockResponse {
            self::assertSame('GET', $method);
            self::assertSame('https://cdn.example.com/audio.mp3', $url);

            return new MockResponse('binary-audio-content', [
                'response_headers' => ['content-type' => 'audio/mpeg'],
            ]);
        });

        $converter = new ResultConverter($downloadClient);

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => ['audio_resource_url' => 'https://cdn.example.com/audio.mp3'],
        ]));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('binary-audio-content', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testItFallsBackToAudioMpegWhenTheCdnSendsNoContentType()
    {
        $downloadClient = new MockHttpClient(new MockResponse('binary-audio-content', [
            'response_headers' => ['content-type' => []],
        ]));

        $converter = new ResultConverter($downloadClient);

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => ['audio_resource_url' => 'https://cdn.example.com/audio.mp3'],
        ]));

        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    /**
     * The CDN answers "binary/octet-stream" whatever the format is, so the extension of the
     * resource URL - which follows the requested "audio_format" - has to win.
     *
     * @param non-empty-string $url
     */
    #[DataProvider('genericContentTypeProvider')]
    public function testItDerivesTheMimeTypeFromTheResourceUrlWhenTheCdnIsUnspecific(string $url, string $contentType, string $expectedMimeType)
    {
        $downloadClient = new MockHttpClient(new MockResponse('binary-audio-content', [
            'response_headers' => ['content-type' => $contentType],
        ]));

        $converter = new ResultConverter($downloadClient);

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => ['audio_resource_url' => $url],
        ]));

        $this->assertSame($expectedMimeType, $result->getMimeType());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function genericContentTypeProvider(): iterable
    {
        yield 'mp3' => ['https://cdn.example.com/a_.mp3?signature=x', 'binary/octet-stream', 'audio/mpeg'];
        yield 'wav' => ['https://cdn.example.com/a_.wav?signature=x', 'binary/octet-stream', 'audio/wav'];
        yield 'ogg' => ['https://cdn.example.com/a_.ogg', 'application/octet-stream', 'audio/ogg'];
        yield 'flac' => ['https://cdn.example.com/a_.flac', 'binary/octet-stream', 'audio/flac'];
        yield 'no extension' => ['https://cdn.example.com/a', 'binary/octet-stream', 'audio/mpeg'];
        yield 'unknown extension' => ['https://cdn.example.com/a_.xyz', 'binary/octet-stream', 'audio/mpeg'];
    }

    /**
     * A CDN that does report a real audio type must still be trusted.
     */
    public function testASpecificContentTypeWinsOverTheUrlExtension()
    {
        $downloadClient = new MockHttpClient(new MockResponse('binary-audio-content', [
            'response_headers' => ['content-type' => 'audio/wav'],
        ]));

        $converter = new ResultConverter($downloadClient);

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => ['audio_resource_url' => 'https://cdn.example.com/a_.mp3'],
        ]));

        $this->assertSame('audio/wav', $result->getMimeType());
    }

    public function testItExposesTheGatewayCostAndProviderAsMetadata()
    {
        $downloadClient = new MockHttpClient(new MockResponse('binary-audio-content', [
            'response_headers' => ['content-type' => 'audio/mpeg'],
        ]));

        $converter = new ResultConverter($downloadClient);

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'provider' => 'amazon',
            'cost' => '0.001600000',
            'output' => ['audio_resource_url' => 'https://cdn.example.com/audio.mp3'],
        ]));

        $this->assertSame('amazon', $result->getMetadata()->get('provider'));
        $this->assertSame(0.0016, $result->getMetadata()->get('cost'));
    }

    /**
     * The resource URL is signed and short-lived, so the download can fail on its own.
     */
    public function testItThrowsWhenTheAudioDownloadIsRejected()
    {
        $downloadClient = new MockHttpClient(new MockResponse('<html>Forbidden</html>', ['http_code' => 403]));

        $converter = new ResultConverter($downloadClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not download the synthesized audio from Eden AI, got HTTP 403.');

        $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => ['audio_resource_url' => 'https://cdn.example.com/expired.mp3'],
        ]));
    }

    /**
     * The signed URL must not leak into the exception message, which tends to be logged.
     */
    public function testItKeepsTheSignedUrlOutOfTheErrorMessage()
    {
        $downloadClient = new MockHttpClient(new MockResponse('', ['http_code' => 404]));

        $converter = new ResultConverter($downloadClient);

        try {
            $converter->convert($this->rawResult([
                'status' => 'success',
                'output' => ['audio_resource_url' => 'https://cdn.example.com/audio.mp3?signature=super-secret'],
            ]));
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('super-secret', $e->getMessage());
        }
    }

    public function testItThrowsWhenProviderFails()
    {
        $converter = new ResultConverter(new MockHttpClient());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI request failed: "Provider requires a model specification."');

        $converter->convert($this->rawResult([
            'status' => 'fail',
            'output' => null,
            'error' => ['message' => 'Provider requires a model specification.'],
        ]));
    }

    public function testItThrowsWhenAudioResourceUrlIsMissing()
    {
        $converter = new ResultConverter(new MockHttpClient());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain audio_resource_url.');

        $converter->convert($this->rawResult(['status' => 'success', 'output' => []]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function rawResult(array $data): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data));

        return new RawHttpResult($httpClient->request('POST', 'https://api.edenai.run/v3/universal-ai'));
    }
}

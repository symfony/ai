<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\UniversalAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\FileUploader;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class FileUploaderTest extends TestCase
{
    public function testItUploadsFileAndReturnsFileId()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.edenai.run/v3/upload', $url);
            self::assertStringContainsString('multipart/form-data', implode(' ', $options['normalized_headers']['content-type'] ?? []));

            return new JsonMockResponse([
                'file_id' => 'a9f3c2e1-6d4b-4f4e-9d2a-1c2b3d4e5f6a',
                'file_name' => 'audio.mp3',
            ]);
        });

        $uploader = new FileUploader($httpClient, 'https://api.edenai.run', 'test-key');

        $this->assertSame(
            'a9f3c2e1-6d4b-4f4e-9d2a-1c2b3d4e5f6a',
            $uploader->upload('binary-content', 'audio.mp3', 'audio/mpeg'),
        );
    }

    public function testItDefaultsTheFilenameWhenNoneIsGiven()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertStringContainsString('filename="file"', self::readBody($options));

            return new JsonMockResponse(['file_id' => 'a9f3c2e1-6d4b-4f4e-9d2a-1c2b3d4e5f6a']);
        });

        $uploader = new FileUploader($httpClient, 'https://api.edenai.run', 'test-key');

        $this->assertSame('a9f3c2e1-6d4b-4f4e-9d2a-1c2b3d4e5f6a', $uploader->upload('binary-content', null, 'application/octet-stream'));
    }

    public function testItForwardsTheRetentionWhenConfigured()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = self::readBody($options);
            self::assertStringContainsString('name="expires_in_days"', $body);
            self::assertStringContainsString('7', $body);

            return new JsonMockResponse(['file_id' => 'a9f3c2e1-6d4b-4f4e-9d2a-1c2b3d4e5f6a']);
        });

        $uploader = new FileUploader($httpClient, 'https://api.edenai.run', 'test-key', 7);

        $this->assertSame('a9f3c2e1-6d4b-4f4e-9d2a-1c2b3d4e5f6a', $uploader->upload('binary-content', 'audio.mp3', 'audio/mpeg'));
    }

    public function testItRejectsARetentionBeyondWhatEdenAiKeeps()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI keeps uploads for 1 to 30 days, "31" given.');

        new FileUploader(new MockHttpClient(), 'https://api.edenai.run', 'test-key', 31);
    }

    /**
     * Every binary input of the bridge is uploaded first, so an upload failure must surface
     * as a platform exception rather than an HttpClient one.
     */
    public function testItMapsAnUnauthorizedUploadOntoAPlatformException()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['detail' => 'Invalid token'], ['http_code' => 401]));

        $uploader = new FileUploader($httpClient, 'https://api.edenai.run', 'test-key');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid token');

        $uploader->upload('binary-content', 'audio.mp3', 'audio/mpeg');
    }

    public function testItMapsARejectedMediaTypeOntoAPlatformException()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'detail' => 'Validation error',
            'errors' => [['field' => 'file', 'message' => 'Unsupported media type']],
        ], ['http_code' => 422]));

        $uploader = new FileUploader($httpClient, 'https://api.edenai.run', 'test-key');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Validation error: file: Unsupported media type');

        $uploader->upload('binary-content', 'audio.mp3', 'audio/mpeg');
    }

    public function testItMapsAnOversizedUploadOntoAPlatformException()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 413]));

        $uploader = new FileUploader($httpClient, 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 413 from Eden AI.');

        $uploader->upload('binary-content', 'audio.mp3', 'audio/mpeg');
    }

    public function testItThrowsWhenFileIdIsMissing()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['file_name' => 'audio.mp3']));

        $uploader = new FileUploader($httpClient, 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI file upload response does not contain a file_id.');

        $uploader->upload('binary-content', 'audio.mp3', 'audio/mpeg');
    }

    /**
     * The multipart body is streamed as a chunk-producing closure, so it has to be
     * materialized before it can be asserted on.
     *
     * @param array<string, mixed> $options
     */
    private static function readBody(array $options): string
    {
        $body = $options['body'] ?? '';

        if (\is_string($body)) {
            return $body;
        }

        if (!\is_callable($body)) {
            return '';
        }

        $materialized = '';

        while ('' !== ($chunk = $body(16372))) {
            $materialized .= $chunk;
        }

        return $materialized;
    }
}

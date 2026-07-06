<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Higgsfield\Contract\ImageNormalizer;
use Symfony\AI\Platform\Bridge\Higgsfield\Higgsfield;
use Symfony\AI\Platform\Bridge\Higgsfield\HiggsfieldClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HiggsfieldClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new HiggsfieldClient(new MockHttpClient(), new MockClock(), 'my-key-id', 'my-key-secret');

        $this->assertTrue($client->supports(new Higgsfield('flux-pro/kontext/max/text-to-image')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testClientGeneratesImageWithImmediateCompletion()
    {
        $imageContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/image.jpg');

        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "completed", "images": [{"url": "https://cdn.higgsfield.ai/image.jpg"}]}'),
            new MockResponse($imageContent, ['response_headers' => ['content-type' => 'image/jpeg']]),
        ]);

        $client = new HiggsfieldClient($httpClient, new MockClock(), 'my-key-id', 'my-key-secret');

        $client->request(new Higgsfield('flux-pro/kontext/max/text-to-image', [Capability::TEXT_TO_IMAGE]), 'A cat on a kitchen table');

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testClientPollsUntilCompletion()
    {
        $videoContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/ocean.mp4');

        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "queued"}'),
            new MockResponse('{"request_id": "req-123", "status": "in_progress"}'),
            new MockResponse('{"request_id": "req-123", "status": "completed", "video": {"url": "https://cdn.higgsfield.ai/video.mp4"}}'),
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $client = new HiggsfieldClient($httpClient, new MockClock(), 'my-key-id', 'my-key-secret');

        $client->request(new Higgsfield('v1/image2video/dop', [Capability::IMAGE_TO_VIDEO]), 'Zoom into the ocean');

        $this->assertSame(4, $httpClient->getRequestsCount());
    }

    public function testClientSendsAuthorizationHeaderAndPrompt()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            if (str_contains($url, 'cdn.higgsfield.ai')) {
                return new MockResponse('binary');
            }

            $this->assertSame('POST', $method);
            $this->assertSame('https://platform.higgsfield.ai/flux-pro/kontext/max/text-to-image', $url);
            $this->assertSame('Authorization: Key my-key-id:my-key-secret', $options['normalized_headers']['authorization'][0]);
            $this->assertSame(['prompt' => 'A cat', 'aspect_ratio' => '9:16'], json_decode($options['body'], true));

            return new MockResponse('{"request_id": "req-123", "status": "completed", "images": [{"url": "https://cdn.higgsfield.ai/image.jpg"}]}');
        });

        $client = new HiggsfieldClient($httpClient, new MockClock(), 'my-key-id', 'my-key-secret');

        $client->request(new Higgsfield('flux-pro/kontext/max/text-to-image', [Capability::TEXT_TO_IMAGE]), 'A cat', ['aspect_ratio' => '9:16']);
    }

    public function testClientWrapsNormalizedImageIntoInputImages()
    {
        $payload = (new ImageNormalizer())->normalize(Image::fromFile(\dirname(__DIR__, 6).'/fixtures/image.jpg'));

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            if (str_contains($url, 'cdn.higgsfield.ai')) {
                return new MockResponse('binary');
            }

            $body = json_decode($options['body'], true);

            $this->assertArrayHasKey('input_images', $body);
            $this->assertSame('image_url', $body['input_images'][0]['type']);
            $this->assertSame('dop-turbo', $body['model']);

            return new MockResponse('{"request_id": "req-123", "status": "completed", "video": {"url": "https://cdn.higgsfield.ai/video.mp4"}}');
        });

        $client = new HiggsfieldClient($httpClient, new MockClock(), 'my-key-id', 'my-key-secret');

        $client->request(new Higgsfield('v1/image2video/dop', [Capability::IMAGE_TO_VIDEO]), $payload, ['model' => 'dop-turbo']);
    }

    public function testClientThrowsOnFailedStatus()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "failed", "detail": "generation crashed"}'),
        ]);

        $client = new HiggsfieldClient($httpClient, new MockClock(), 'my-key-id', 'my-key-secret');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Higgsfield request "req-123" "failed": "generation crashed".');

        $client->request(new Higgsfield('flux-pro/kontext/max/text-to-image', [Capability::TEXT_TO_IMAGE]), 'A cat');
    }

    public function testClientThrowsWhenRequestIdMissing()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"detail": "invalid credentials"}'),
        ]);

        $client = new HiggsfieldClient($httpClient, new MockClock(), 'my-key-id', 'my-key-secret');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Higgsfield API error: "invalid credentials".');

        $client->request(new Higgsfield('flux-pro/kontext/max/text-to-image', [Capability::TEXT_TO_IMAGE]), 'A cat');
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (str_contains($url, 'cdn.higgsfield.ai')) {
                return new MockResponse('binary');
            }

            $this->assertSame('https://higgsfield.example.com/flux-pro/kontext/max/text-to-image', $url);

            return new MockResponse('{"request_id": "req-123", "status": "completed", "images": [{"url": "https://cdn.higgsfield.ai/image.jpg"}]}');
        });

        $client = new HiggsfieldClient($httpClient, new MockClock(), 'my-key-id', 'my-key-secret', 'https://higgsfield.example.com/');

        $client->request(new Higgsfield('flux-pro/kontext/max/text-to-image', [Capability::TEXT_TO_IMAGE]), 'A cat');
    }
}

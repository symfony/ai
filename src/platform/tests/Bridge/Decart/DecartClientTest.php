<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Decart;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decart\Contract\ImageNormalizer;
use Symfony\AI\Platform\Bridge\Decart\Contract\VideoNormalizer;
use Symfony\AI\Platform\Bridge\Decart\Decart;
use Symfony\AI\Platform\Bridge\Decart\DecartClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Video;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DecartClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new DecartClient(
            new MockHttpClient(),
            'my-api-key',
        );

        $this->assertTrue($client->supports(new Decart('lucy-dev-i2v')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testClientCannotGenerateOnInvalidModel()
    {
        $client = new DecartClient(
            new MockHttpClient(),
            'my-api-key',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "any-model" model is not supported.');
        $this->expectExceptionCode(0);
        $client->request(new Model('any-model'), []);
    }

    public function testClientCanGenerateTextToImage()
    {
        $normalizer = new ImageNormalizer();

        $payload = $normalizer->normalize(Image::fromFile(\dirname(__DIR__, 5).'/fixtures/image.jpg'));

        $httpClient = new MockHttpClient([
            new MockResponse($payload),
        ]);

        $client = new DecartClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-pro-t2i', [Capability::TEXT_TO_IMAGE]), [
            'text' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanGenerateTextToVideo()
    {
        $normalizer = new VideoNormalizer();

        $payload = $normalizer->normalize(Video::fromFile(\dirname(__DIR__, 5).'/fixtures/ocean.mp4'));

        $httpClient = new MockHttpClient([
            new MockResponse($payload),
        ]);

        $client = new DecartClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-pro-t2v', [Capability::TEXT_TO_VIDEO]), [
            'text' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanGenerateImageToImage()
    {
        $normalizer = new ImageNormalizer();

        $payload = $normalizer->normalize(Image::fromFile(\dirname(__DIR__, 5).'/fixtures/image.jpg'));

        $httpClient = new MockHttpClient([
            new MockResponse($payload),
        ]);

        $client = new DecartClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-dev-i2v', [Capability::IMAGE_TO_IMAGE]), $payload, [
            'prompt' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanGenerateImageToVideo()
    {
        $normalizer = new ImageNormalizer();
        $videoNormalizer = new VideoNormalizer();

        $payload = $normalizer->normalize(Image::fromFile(\dirname(__DIR__, 5).'/fixtures/image.jpg'));
        $responsePayload = $videoNormalizer->normalize(Video::fromFile(\dirname(__DIR__, 5).'/fixtures/ocean.mp4'));

        $httpClient = new MockHttpClient([
            new MockResponse($responsePayload),
        ]);

        $client = new DecartClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-dev-i2i', [Capability::IMAGE_TO_VIDEO]), $payload, [
            'prompt' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanGenerateVideoToVideo()
    {
        $normalizer = new VideoNormalizer();

        $payload = $normalizer->normalize(Video::fromFile(\dirname(__DIR__, 5).'/fixtures/ocean.mp4'));

        $httpClient = new MockHttpClient([
            new MockResponse($payload),
        ]);

        $client = new DecartClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-pro-v2v', [Capability::VIDEO_TO_VIDEO]), $payload, [
            'prompt' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}

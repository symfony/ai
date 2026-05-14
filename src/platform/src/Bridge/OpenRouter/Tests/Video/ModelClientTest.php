<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Video;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenRouter\Video\ModelClient;
use Symfony\AI\Platform\Bridge\OpenRouter\VideoGenerationModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'my-api-key');

        $this->assertTrue($client->supports(new VideoGenerationModel('google/veo-3.1')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testItPostsCreatesJobAndPollsUntilCompleted()
    {
        $videoContent = file_get_contents(\dirname(__DIR__, 7).'/fixtures/ocean.mp4');

        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'job-123', 'status' => 'pending']),
            new JsonMockResponse(['id' => 'job-123', 'status' => 'in_progress']),
            new JsonMockResponse([
                'id' => 'job-123',
                'status' => 'completed',
                'unsigned_urls' => ['https://openrouter.ai/api/v1/videos/job-123/content?index=0'],
            ]),
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $client = new ModelClient($httpClient, 'my-api-key', 'https://openrouter.ai/api', 0, 10);

        $result = $client->request(
            new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]),
            'A serene ocean',
        );

        $this->assertSame($videoContent, $result->getObject()->getContent());
        $this->assertSame(4, $httpClient->getRequestsCount());
    }

    public function testItAcceptsPromptFromOptionsWhenPayloadIsArray()
    {
        $videoContent = 'binary';

        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'job-1', 'status' => 'pending']),
            new JsonMockResponse([
                'id' => 'job-1',
                'status' => 'completed',
                'unsigned_urls' => ['https://openrouter.ai/api/v1/videos/job-1/content?index=0'],
            ]),
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $client = new ModelClient($httpClient, 'my-api-key', 'https://openrouter.ai/api', 0, 10);

        $result = $client->request(
            new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]),
            ['type' => 'text', 'text' => 'Prompt via text key'],
        );

        $this->assertSame($videoContent, $result->getObject()->getContent());
    }

    public function testItThrowsWhenPromptIsMissing()
    {
        $client = new ModelClient(new MockHttpClient(), 'my-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The video generation request requires a text prompt.');

        $client->request(new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]), []);
    }

    public function testItThrowsWhenApiDoesNotReturnJobId()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['status' => 'pending']),
        ]);

        $client = new ModelClient($httpClient, 'my-api-key', 'https://openrouter.ai/api', 0, 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The video generation request did not return a job ID.');

        $client->request(new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]), 'prompt');
    }

    public function testItThrowsWhenJobFails()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'job-err', 'status' => 'pending']),
            new JsonMockResponse(['id' => 'job-err', 'status' => 'failed']),
        ]);

        $client = new ModelClient($httpClient, 'my-api-key', 'https://openrouter.ai/api', 0, 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Video generation failed for job "job-err".');

        $client->request(new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]), 'prompt');
    }

    public function testItThrowsWhenCompletedWithoutDownloadUrl()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'job-x', 'status' => 'pending']),
            new JsonMockResponse(['id' => 'job-x', 'status' => 'completed', 'unsigned_urls' => []]),
        ]);

        $client = new ModelClient($httpClient, 'my-api-key', 'https://openrouter.ai/api', 0, 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no download URL');

        $client->request(new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]), 'prompt');
    }

    public function testItSendsModelAndOptionsInRequestBody()
    {
        $videoContent = 'binary';
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody, $videoContent) {
            if ('POST' === $method && str_ends_with($url, '/v1/videos')) {
                $capturedBody = json_decode($options['body'], true);

                return new JsonMockResponse(['id' => 'job-42', 'status' => 'pending']);
            }

            if ('GET' === $method && str_contains($url, '/v1/videos/job-42') && !str_contains($url, '/content')) {
                return new JsonMockResponse([
                    'id' => 'job-42',
                    'status' => 'completed',
                    'unsigned_urls' => ['https://openrouter.ai/api/v1/videos/job-42/content?index=0'],
                ]);
            }

            return new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]);
        });

        $client = new ModelClient($httpClient, 'my-api-key', 'https://openrouter.ai/api', 0, 10);

        $client->request(
            new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]),
            'A serene ocean',
            [
                'duration' => 4,
                'resolution' => '1080p',
                'aspect_ratio' => '16:9',
                'poll_interval' => 0,
                'poll_timeout' => 10,
            ],
        );

        $this->assertSame('google/veo-3.1', $capturedBody['model']);
        $this->assertSame('A serene ocean', $capturedBody['prompt']);
        $this->assertSame(4, $capturedBody['duration']);
        $this->assertSame('1080p', $capturedBody['resolution']);
        $this->assertSame('16:9', $capturedBody['aspect_ratio']);
        $this->assertArrayNotHasKey('poll_interval', $capturedBody);
        $this->assertArrayNotHasKey('poll_timeout', $capturedBody);
    }
}

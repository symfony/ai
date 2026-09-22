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
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

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

    public function testItSubmitsTheJobWithoutPolling()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'job-123', 'status' => 'pending'], ['http_code' => 202]),
        ]);

        $client = new ModelClient($httpClient, 'my-api-key');

        $result = $client->request(
            new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]),
            'A serene ocean',
        );

        $this->assertSame(['id' => 'job-123', 'status' => 'pending'], $result->getData());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItAcceptsPromptFromTextPayload()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse(['id' => 'job-1', 'status' => 'pending']);
        });

        $client = new ModelClient($httpClient, 'my-api-key');

        $client->request(
            new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]),
            ['type' => 'text', 'text' => 'Prompt via text key'],
        );

        $this->assertSame('Prompt via text key', $capturedBody['prompt']);
    }

    public function testItThrowsWhenPromptIsMissing()
    {
        $client = new ModelClient(new MockHttpClient(), 'my-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The video generation request requires a text prompt.');

        $client->request(new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]), []);
    }

    public function testItSendsModelAndOptionsInRequestBody()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse(['id' => 'job-42', 'status' => 'pending']);
        });

        $client = new ModelClient($httpClient, 'my-api-key', 'https://openrouter.ai/api');

        $client->request(
            new VideoGenerationModel('google/veo-3.1', [Capability::TEXT_TO_VIDEO]),
            'A serene ocean',
            [
                'duration' => 4,
                'resolution' => '1080p',
                'aspect_ratio' => '16:9',
            ],
        );

        $this->assertSame('https://openrouter.ai/api/v1/videos', $capturedUrl);
        $this->assertSame('google/veo-3.1', $capturedBody['model']);
        $this->assertSame('A serene ocean', $capturedBody['prompt']);
        $this->assertSame(4, $capturedBody['duration']);
        $this->assertSame('1080p', $capturedBody['resolution']);
        $this->assertSame('16:9', $capturedBody['aspect_ratio']);
    }

    public function testItSendsTheFirstImageAsFrame()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse(['id' => 'job-7', 'status' => 'pending']);
        });

        $client = new ModelClient($httpClient, 'my-api-key');

        $client->request(new VideoGenerationModel('google/veo-3.1', [Capability::IMAGE_TO_VIDEO]), [
            'messages' => [
                ['role' => 'system', 'content' => 'Animate the elephant.'],
                ['role' => 'user', 'content' => [['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,AAAA']]]],
            ],
        ]);

        $this->assertSame('Animate the elephant.', $capturedBody['prompt']);
        $this->assertSame([[
            'type' => 'image_url',
            'image_url' => ['url' => 'data:image/jpeg;base64,AAAA'],
            'frame_type' => 'first_frame',
        ]], $capturedBody['frame_images']);
    }
}

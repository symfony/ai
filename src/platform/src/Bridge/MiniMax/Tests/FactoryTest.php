<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\Factory;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\RetryableHttpClient;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class FactoryTest extends TestCase
{
    public function testTheJobHandleCarriesTheNameTheProviderWasCreatedWith()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['task_id' => '789', 'base_resp' => ['status_code' => 0]]));

        $handle = Factory::createProvider('key', $httpClient, name: 'minimax-eu')
            ->invoke('MiniMax-Hailuo-02', 'A cat playing piano')
            ->asJob();

        $this->assertSame('789', $handle->getId());
        $this->assertSame('minimax-eu', $handle->getProvider());
    }

    public function testH3GeneratesAnImage()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.minimax.io/v1/images/generations', $url);
            $this->assertSame(600.0, $options['timeout']);
            $this->assertSame(600.0, $options['max_duration']);
            $body = json_decode($options['body'], true);
            $this->assertSame('png', $body['output_format']);
            $this->assertSame('b64_json', $body['response_format']);
            $this->assertSame('A cat', $body['prompt']);
            $this->assertSame('MiniMax-H3-Image', $body['model']);

            return new JsonMockResponse(['output_format' => 'png', 'data' => [['b64_json' => base64_encode('image bytes')]]]);
        });

        $result = Factory::createPlatform('key', $client)->invoke('MiniMax-H3-Image', new Text('A cat'), ['output_format' => 'png']);
        $this->assertSame('data:image/png;base64,'.base64_encode('image bytes'), $result->asDataUri());
    }

    public function testH3RetainsSuccessfulImagesAndErrorsInAPartialBatch()
    {
        $error = ['code' => '1027', 'message' => 'output image failed content safety check'];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($error): JsonMockResponse {
            $this->assertSame('https://api.minimax.cn/v1/images/generations', $url);
            $body = json_decode($options['body'], true);
            $this->assertSame('MiniMax-H3-Image-Highspeed', $body['model']);
            $this->assertSame(3, $body['n']);
            $this->assertSame('url', $body['response_format']);
            $this->assertTrue($body['aigc_watermark']);

            return new JsonMockResponse(['data' => [
                ['url' => 'https://example.com/first.jpg'],
                ['error' => $error],
                ['url' => 'https://example.com/third.jpg'],
            ]]);
        });
        $platform = Factory::createPlatform('key', $client, 'https://api.minimax.cn/v1');
        $result = $platform->invoke('MiniMax-H3-Image-Highspeed', 'A cat', ['response_format' => 'url', 'n' => 3, 'aigc_watermark' => true])->getResult();

        $this->assertInstanceOf(ChoiceResult::class, $result);
        $this->assertSame('https://example.com/first.jpg', $result->getContent()[0]->getContent());
        $this->assertSame('https://example.com/third.jpg', $result->getContent()[1]->getContent());
        $this->assertSame([1 => $error], $result->getMetadata()->get('image_errors'));
    }

    public function testH3DoesNotRetryAFailedGeneration()
    {
        $client = new MockHttpClient(new JsonMockResponse(['error' => ['message' => 'Generation unavailable']], ['http_code' => 503]));

        try {
            Factory::createPlatform('key', new RetryableHttpClient($client))->invoke('MiniMax-H3-Image', 'A cat')->asBinary();
            $this->fail('Expected a server error.');
        } catch (ServerException $e) {
            $this->assertStringContainsString('Generation unavailable', $e->getMessage());
        }

        $this->assertSame(1, $client->getRequestsCount());
    }
}

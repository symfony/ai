<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Replicate\Client;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ClientTest extends TestCase
{
    public function testRequestReturnsTheCreatedPrediction()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "pred-123", "status": "starting"}'));

        $client = new Client($httpClient, 'test-api-key');
        $response = $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => 'Hello']);

        $data = $response->toArray();
        $this->assertSame('pred-123', $data['id']);
        $this->assertSame('starting', $data['status']);
    }

    public function testRequestDoesNotWaitForThePrediction()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "pred-123", "status": "starting"}'));

        $client = new Client($httpClient, 'test-api-key');
        $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => 'Hello']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://replicate.example.com/v1/models/meta/llama-3.1-405b-instruct/predictions', $url);

            return new MockResponse('{"id": "pred-123", "status": "starting"}');
        });

        $client = new Client($httpClient, 'test-api-key', 'https://replicate.example.com/');
        $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => 'Hello']);
    }

    public function testRequestAuthenticationHeader()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) {
            self::assertSame('Authorization: Bearer secret-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse('{"id": "pred-123", "status": "starting"}');
        });

        $client = new Client($httpClient, 'secret-key');
        $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => 'test']);
    }

    public function testRequestUrl()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) {
            self::assertSame('POST', $method);
            self::assertSame('https://api.replicate.com/v1/models/meta/llama-3.1-405b-instruct/predictions', $url);

            return new MockResponse('{"id": "pred-123", "status": "starting"}');
        });

        $client = new Client($httpClient, 'test-key');
        $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => 'test']);
    }

    public function testGetUrl()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) {
            self::assertSame('GET', $method);
            self::assertSame('https://api.replicate.com/v1/predictions/pred-123', $url);
            self::assertSame('Authorization: Bearer test-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse('{"id": "pred-123", "status": "succeeded", "output": ["Hello"]}');
        });

        $client = new Client($httpClient, 'test-key');

        $this->assertSame(['Hello'], $client->get('v1/predictions/pred-123')['output']);
    }

    public function testGetThrowsOnApiError()
    {
        $httpClient = new MockHttpClient(new MockResponse(
            '{"detail": "Invalid version or not permitted"}',
            ['http_code' => 404],
        ));

        $client = new Client($httpClient, 'test-api-key');

        $this->expectException(ModelNotFoundException::class);

        $client->get('v1/predictions/pred-123');
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            self::assertJson($options['body']);
            self::assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse('{"id": "pred-123", "status": "starting"}');
        });

        $client = new Client($httpClient, 'test-api-key');
        $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => "tool output \xB1 here"]);
    }
}

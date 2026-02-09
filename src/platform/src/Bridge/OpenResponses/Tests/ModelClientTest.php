<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\ModelClient;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ModelClientTest extends TestCase
{
    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'https://api.example.com');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $modelClient = new ModelClient($httpClient, 'https://api.example.com');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItSupportsResponsesModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'https://api.example.com');

        $this->assertTrue($modelClient->supports(new ResponsesModel('test-model')));
    }

    public function testItDoesNotSupportOtherModels()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'https://api.example.com');

        $this->assertFalse($modelClient->supports(new Model('test-model')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.example.com/v1/responses', $url);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":1,"model":"test-model","input":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'https://api.example.com', 'test-api-key');
        $modelClient->request(
            new ResponsesModel('test-model'),
            ['input' => [['role' => 'user', 'content' => 'test message']]],
            ['temperature' => 1],
        );
    }

    public function testItWorksWithoutApiKey()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://localhost:1234/v1/responses', $url);
            self::assertArrayNotHasKey('authorization', $options['normalized_headers']);
            self::assertSame('{"model":"local-model","input":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'https://localhost:1234');
        $modelClient->request(
            new ResponsesModel('local-model'),
            ['input' => [['role' => 'user', 'content' => 'Hello']]],
        );
    }

    public function testItUsesCustomResponsesPath()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.example.com/custom/responses', $url);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'https://api.example.com', 'test-api-key', '/custom/responses');
        $modelClient->request(
            new ResponsesModel('test-model'),
            ['input' => [['role' => 'user', 'content' => 'Hello']]],
        );
    }

    public function testItHandlesStructuredOutputOption()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.example.com/v1/responses', $url);
            self::assertSame('{"temperature":0.7,"text":{"format":{"name":"foo","schema":[],"type":"json"}},"model":"test-model","input":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $options = [
            'temperature' => 0.7,
            'response_format' => [
                'type' => 'json',
                'json_schema' => [
                    'name' => 'foo',
                    'schema' => [],
                ],
            ],
        ];

        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'https://api.example.com', 'test-api-key');
        $modelClient->request(
            new ResponsesModel('test-model'),
            ['input' => [['role' => 'user', 'content' => 'Hello']]],
            $options,
        );
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\DockerModelRunner\Completions;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\DockerModelRunner\Completions;
use Symfony\AI\Platform\Bridge\DockerModelRunner\Completions\ModelClient;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ModelClientTest extends TestCase
{
    public function testItIsSupportingTheCorrectModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'http://localhost:1234');

        $this->assertTrue($client->supports(new Completions('test-model')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/engines/v1/chat/completions', $url);
            self::assertSame('{"model":"test-model","messages":[{"role":"user","content":"Hello, world!"}]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $payload = [
            'model' => 'test-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, world!'],
            ],
        ];

        $client->request(new Completions('test-model'), $payload);
    }

    public function testItMergesOptionsWithPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/engines/v1/chat/completions', $url);
            self::assertSame('{"temperature":0.7,"model":"test-model","messages":[{"role":"user","content":"Hello, world!"}]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $payload = [
            'model' => 'test-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, world!'],
            ],
        ];

        $client->request(new Completions('test-model'), $payload, ['temperature' => 0.7]);
    }

    public function testItUsesEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $reflection = new \ReflectionProperty($client, 'httpClient');

        $this->assertInstanceOf(EventSourceHttpClient::class, $reflection->getValue($client));
    }

    public function testItKeepsExistingEventSourceHttpClient()
    {
        $eventSourceHttpClient = new EventSourceHttpClient(new MockHttpClient());
        $client = new ModelClient($eventSourceHttpClient, 'http://localhost:1234');

        $reflection = new \ReflectionProperty($client, 'httpClient');

        $this->assertSame($eventSourceHttpClient, $reflection->getValue($client));
    }
}

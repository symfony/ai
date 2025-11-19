<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\Scaleway\Llm;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Scaleway\Llm\ModelClient;
use Symfony\AI\Platform\Bridge\Scaleway\Scaleway;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class ModelClientTest extends TestCase
{
    public function testItAcceptsValidApiKey()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'scaleway-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'scaleway-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $modelClient = new ModelClient($httpClient, 'scaleway-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->assertTrue($modelClient->supports(new Scaleway('deepseek-r1-distill-llama-70b')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":1,"model":"deepseek-r1-distill-llama-70b","messages":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Scaleway('deepseek-r1-distill-llama-70b'), ['model' => 'deepseek-r1-distill-llama-70b', 'messages' => [['role' => 'user', 'content' => 'test message']]], ['temperature' => 1]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":0.7,"model":"deepseek-r1-distill-llama-70b","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Scaleway('deepseek-r1-distill-llama-70b'), ['model' => 'deepseek-r1-distill-llama-70b', 'messages' => [['role' => 'user', 'content' => 'Hello']]], ['temperature' => 0.7]);
    }

    public function testItUsesCorrectBaseUrl()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Scaleway('deepseek-r1-distill-llama-70b'), ['messages' => []], []);
    }
}

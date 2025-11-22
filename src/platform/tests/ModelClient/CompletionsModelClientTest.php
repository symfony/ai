<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\ModelClient;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model\CompletionsModel;
use Symfony\AI\Platform\ModelClient\CompletionsModelClient;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class CompletionsModelClientTest extends TestCase
{
    public function testItAcceptsValidApiKey()
    {
        $modelClient = new CompletionsModelClient('sk-valid-api-key', 'https://example.org');

        $this->assertInstanceOf(CompletionsModelClient::class, $modelClient);
    }

    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new CompletionsModelClient('sk-valid-api-key', 'https://example.org', httpClient: $httpClient);

        $this->assertInstanceOf(CompletionsModelClient::class, $modelClient);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $modelClient = new CompletionsModelClient('sk-valid-api-key', 'https://example.org', httpClient: $httpClient);

        $this->assertInstanceOf(CompletionsModelClient::class, $modelClient);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new CompletionsModelClient('sk-valid-api-key', 'https://example.org');

        $this->assertTrue($modelClient->supports(new CompletionsModel('gpt-4o')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://example.org/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":1,"model":"gpt-4o","messages":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new CompletionsModelClient('sk-valid-api-key', 'https://example.org', httpClient: $httpClient);
        $modelClient->request(new CompletionsModel('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'test message']]], ['temperature' => 1]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://example.org/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":0.7,"model":"gpt-4o","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new CompletionsModelClient('sk-valid-api-key', 'https://example.org', httpClient: $httpClient);
        $modelClient->request(new CompletionsModel('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'Hello']]], ['temperature' => 0.7]);
    }

    #[TestWith(['https://api.inference.eu', 'https://api.inference.eu/v1/chat/completions'])]
    #[TestWith(['https://api.inference.com', 'https://api.inference.com/v1/chat/completions'])]
    public function testItUsesCorrectBaseUrl(string $baseUrl, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new CompletionsModelClient('sk-valid-api-key', $baseUrl, httpClient: $httpClient);
        $modelClient->request(new CompletionsModel('gpt-4o'), ['messages' => []]);
    }

    #[TestWith(['/custom/path', 'https://api.inference.com/custom/path'])]
    #[TestWith(['/v1/alternative/endpoint', 'https://api.inference.com/v1/alternative/endpoint'])]
    public function testsItUsesCorrectPathIfProvided(string $path, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new CompletionsModelClient('sk-valid-api-key', 'https://api.inference.com', $path, $httpClient);
        $modelClient->request(new CompletionsModel('gpt-4o'), ['messages' => []]);
    }
}

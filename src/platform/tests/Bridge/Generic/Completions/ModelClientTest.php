<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Generic\Completions;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\Completions\ModelClient;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClientTest extends TestCase
{
    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'http://localhost:8000');

        $this->assertTrue($modelClient->supports(new CompletionsModel('gpt-4o')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":1,"model":"gpt-4o","messages":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'http://localhost:8000', 'sk-valid-api-key');
        $modelClient->request(new CompletionsModel('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'test message']]], ['temperature' => 1]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":0.7,"model":"gpt-4o","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'http://localhost:8000', 'sk-valid-api-key');
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
        $modelClient = new ModelClient($httpClient, $baseUrl, 'sk-valid-api-key');
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
        $modelClient = new ModelClient($httpClient, 'https://api.inference.com', 'sk-valid-api-key', $path);
        $modelClient->request(new CompletionsModel('gpt-4o'), ['messages' => []]);
    }
}

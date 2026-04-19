<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Gemini;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientTest extends TestCase
{
    public function testRequest()
    {
        $payload = [
            'contents' => [
                ['parts' => [['text' => 'Hello, world!']]],
            ],
        ];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($payload) {
            $this->assertSame('POST', $method);
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent', $url);
            $this->assertArrayHasKey('headers', $options);
            $this->assertContains('x-goog-api-key: test-api-key', $options['headers']);

            $body = json_decode($options['body'], true);
            $this->assertSame($payload['contents'], $body['contents']);
            $this->assertArrayNotHasKey('generationConfig', $body);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Gemini('gemini-1.5-flash'), $payload);
    }

    public function testRequestWithOptions()
    {
        $payload = ['contents' => []];
        $options = [
            'temperature' => 0.7,
            'topP' => 0.9,
            'tools' => [['name' => 'get_weather']],
            'tool_config' => ['function_calling_config' => ['mode' => 'ANY']],
            'server_tools' => ['google_search' => true],
        ];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);

            $this->assertSame(0.7, $body['generationConfig']['temperature']);
            $this->assertSame(0.9, $body['generationConfig']['topP']);
            $this->assertSame([['functionDeclarations' => [['name' => 'get_weather']]], ['google_search' => []]], $body['tools']);
            $this->assertSame(['function_calling_config' => ['mode' => 'ANY']], $body['tool_config']);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Gemini('gemini-1.5-flash'), $payload, $options);
    }

    public function testRequestWithStreamUsesDifferentEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:streamGenerateContent', $url);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Gemini('gemini-1.5-flash'), ['contents' => []], ['stream' => true]);
    }
}

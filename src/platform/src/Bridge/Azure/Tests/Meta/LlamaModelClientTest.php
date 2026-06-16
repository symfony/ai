<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Tests\Meta;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\Meta\LlamaModelClient;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LlamaModelClientTest extends TestCase
{
    public function testItIsExecutingTheCorrectRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://test.azure.com/chat/completions', $url);
            $this->assertSame('Authorization: test-api-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame('{"messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        }]);

        $client = new LlamaModelClient($httpClient, 'test.azure.com', 'test-api-key');
        $client->request(new Llama('llama-3.3-70b-instruct'), ['messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        }]);

        $client = new LlamaModelClient($httpClient, 'test.azure.com', 'test-api-key');
        $client->request(new Llama('llama-3.3-70b-instruct'), ['messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }
}

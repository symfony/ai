<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Llm;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Llm\ModelClient;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClientTest extends TestCase
{
    public function testItIsExecutingTheCorrectRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mistral.ai/v1/chat/completions', $url);
            $this->assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame('{"messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Mistral('mistral-large-latest'), ['messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Mistral('mistral-large-latest'), ['messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }
}

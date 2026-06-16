<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests\Gemini;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientTest extends TestCase
{
    public function testItInvokesTheTextModelsSuccessfully()
    {
        $payload = [
            'content' => [
                ['parts' => ['text' => 'Hello, world!']],
            ],
        ];
        $expectedResponse = [
            'candidates' => [$payload],
        ];
        $httpClient = new MockHttpClient(
            new JsonMockResponse($expectedResponse),
        );

        $client = new ModelClient($httpClient, 'global', 'test');

        $result = $client->request(new Model('gemini-2.0-flash'), $payload);
        $data = $result->getData();
        $info = $result->getObject()->getInfo();

        $this->assertNotEmpty($data);
        $this->assertNotEmpty($info);
        $this->assertSame('POST', $info['http_method']);
        $this->assertSame(
            'https://aiplatform.googleapis.com/v1/projects/test/locations/global/publishers/google/models/gemini-2.0-flash:generateContent',
            $info['url'],
        );
        $this->assertSame($expectedResponse, $data);
    }

    public function testRequestWithStreamAsksForSseFormat()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertStringContainsString(':streamGenerateContent', $url);
            $this->assertStringContainsString('alt=sse', $url);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => []], ['stream' => true]);
    }

    public function testItPassesServerToolsFromOptions()
    {
        $payload = [
            'content' => [
                ['parts' => ['text' => 'Server tool test']],
            ],
        ];
        $httpClient = new MockHttpClient(
            function ($method, $url, $options) {
                $this->assertJsonStringEqualsJsonString(
                    <<<'JSON'
                        {
                          "tools": [
                            {"google_search": {}}
                          ],
                          "content": [
                            {"parts":{"text":"Server tool test"}}
                          ]
                        }
                        JSON,
                    $options['body'],
                );

                return new JsonMockResponse('{}');
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, ['server_tools' => ['google_search' => true]]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => [['parts' => [['text' => "tool output \xB1 here"]]]]]);
    }
}

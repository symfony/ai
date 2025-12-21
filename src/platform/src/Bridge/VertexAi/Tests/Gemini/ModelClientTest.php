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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\ModelClient;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'global', 'test-project');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $modelClient = new ModelClient($httpClient, 'global', 'test-project');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItSupportsTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'global', 'test-project');

        $this->assertTrue($modelClient->supports(new Model('gemini-2.0-flash')));
    }

    public function testItDoesNotSupportOtherModels()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'global', 'test-project');

        $this->assertFalse($modelClient->supports(new Gpt('gpt-4o')));
    }

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

    #[TestWith(['us-central1', 'my-project', 'gemini-1.5-pro'])]
    #[TestWith(['europe-west1', 'another-project', 'gemini-2.0-flash'])]
    #[TestWith(['asia-east1', 'test-project', 'gemini-1.5-flash'])]
    public function testItBuildsCorrectUrlWithDifferentLocationsAndProjects(string $location, string $projectId, string $modelName)
    {
        $expectedUrl = \sprintf(
            'https://aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent',
            $projectId,
            $location,
            $modelName,
        );

        $httpClient = new MockHttpClient(
            function (string $method, string $url) use ($expectedUrl): HttpResponse {
                self::assertSame('POST', $method);
                self::assertSame($expectedUrl, $url);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, $location, $projectId);
        $client->request(new Model($modelName), ['contents' => []]);
    }

    public function testItUsesStreamGenerateContentWhenStreamOptionIsTrue()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url): HttpResponse {
                self::assertStringContainsString(':streamGenerateContent', $url);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => []], ['stream' => true]);
    }

    public function testItUsesGenerateContentWhenStreamOptionIsFalse()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url): HttpResponse {
                self::assertStringContainsString(':generateContent', $url);
                self::assertStringNotContainsString(':streamGenerateContent', $url);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => []], ['stream' => false]);
    }

    public function testItConvertsStringPayloadToContentsFormat()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                self::assertJsonStringEqualsJsonString(
                    <<<'JSON'
                        {
                          "contents": [
                            {
                              "role": "user",
                              "parts": [
                                {"text": "Hello, World!"}
                              ]
                            }
                          ]
                        }
                        JSON,
                    $options['body'],
                );

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), 'Hello, World!');
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
                self::assertJsonStringEqualsJsonString(
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

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, ['server_tools' => ['google_search' => true]]);
    }

    public function testItPassesServerToolsWithCustomParams()
    {
        $payload = ['contents' => []];
        $httpClient = new MockHttpClient(
            function ($method, $url, $options) {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('tools', $body);
                self::assertCount(1, $body['tools']);
                self::assertArrayHasKey('code_execution', $body['tools'][0]);
                self::assertSame(['enabled' => true], $body['tools'][0]['code_execution']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, ['server_tools' => ['code_execution' => ['enabled' => true]]]);
    }

    public function testItIgnoresServerToolsWithFalsyParams()
    {
        $payload = ['contents' => []];
        $httpClient = new MockHttpClient(
            function ($method, $url, $options) {
                $body = json_decode($options['body'], true);

                self::assertArrayNotHasKey('tools', $body);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, ['server_tools' => ['google_search' => false]]);
    }

    public function testItPassesFunctionDeclarationsFromTools()
    {
        $payload = ['contents' => []];
        $tools = [
            ['name' => 'get_weather', 'description' => 'Get weather information'],
            ['name' => 'get_time', 'description' => 'Get current time'],
        ];

        $httpClient = new MockHttpClient(
            function ($method, $url, $options) use ($tools) {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('tools', $body);
                self::assertCount(1, $body['tools']);
                self::assertArrayHasKey('functionDeclarations', $body['tools'][0]);
                self::assertSame($tools, $body['tools'][0]['functionDeclarations']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, ['tools' => $tools]);
    }

    public function testItCombinesToolsAndServerTools()
    {
        $payload = ['contents' => []];
        $tools = [['name' => 'get_weather']];

        $httpClient = new MockHttpClient(
            function ($method, $url, $options) {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('tools', $body);
                self::assertCount(2, $body['tools']);
                self::assertArrayHasKey('functionDeclarations', $body['tools'][0]);
                self::assertArrayHasKey('google_search', $body['tools'][1]);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, [
            'tools' => $tools,
            'server_tools' => ['google_search' => true],
        ]);
    }

    public function testItHandlesResponseFormatWithJsonSchema()
    {
        $payload = ['contents' => []];
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $httpClient = new MockHttpClient(
            function ($method, $url, $options) {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('generationConfig', $body);
                self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
                self::assertSame([
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                    ],
                ], $body['generationConfig']['responseSchema']);
                self::assertArrayNotHasKey(PlatformSubscriber::RESPONSE_FORMAT, $body);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, [
            PlatformSubscriber::RESPONSE_FORMAT => [
                'json_schema' => [
                    'schema' => $schema,
                ],
            ],
        ]);
    }

    public function testItPassesGenerationConfigAsObject()
    {
        $payload = ['contents' => []];

        $httpClient = new MockHttpClient(
            function ($method, $url, $options) {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('generationConfig', $body);
                // The generationConfig should be an object (stdClass when decoded)
                self::assertSame(['temperature' => 0.7, 'maxOutputTokens' => 1000], $body['generationConfig']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, [
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1000,
            ],
        ]);
    }

    public function testItHandlesStreamWithGenerationConfig()
    {
        $payload = ['contents' => []];

        $httpClient = new MockHttpClient(
            function ($method, $url, $options) {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('generation_config', $body);
                self::assertArrayNotHasKey('generationConfig', $body);
                self::assertArrayNotHasKey('stream', $body);
                self::assertStringContainsString(':streamGenerateContent', $url);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, [
            'stream' => true,
            'generationConfig' => ['temperature' => 0.5],
        ]);
    }
}

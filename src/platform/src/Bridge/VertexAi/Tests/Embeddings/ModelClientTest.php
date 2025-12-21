<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests\Embeddings;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\ModelClient;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TaskType;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'global', 'test-project');

        $this->assertTrue($modelClient->supports(new Model('gemini-embedding-001')));
    }

    public function testItDoesNotSupportOtherModels()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'global', 'test-project');

        $this->assertFalse($modelClient->supports(new Gpt('gpt-4o')));
    }

    public function testItGeneratesTheEmbeddingSuccessfully()
    {
        $expectedResponse = [
            'predictions' => [
                ['embeddings' => ['values' => [0.3, 0.4, 0.4]]],
            ],
        ];
        $httpClient = new MockHttpClient(new JsonMockResponse($expectedResponse));

        $client = new ModelClient($httpClient, 'global', 'test');

        $model = new Model('gemini-embedding-001', options: ['outputDimensionality' => 1536, 'task_type' => TaskType::CLASSIFICATION]);

        $result = $client->request($model, 'test payload');

        $this->assertSame($expectedResponse, $result->getData());
    }

    #[TestWith(['us-central1', 'my-project', 'text-embedding-005'])]
    #[TestWith(['europe-west1', 'another-project', 'gemini-embedding-001'])]
    #[TestWith(['asia-east1', 'test-project', 'text-multilingual-embedding-002'])]
    public function testItBuildsCorrectUrlWithDifferentLocationsAndProjects(string $location, string $projectId, string $modelName)
    {
        $expectedUrl = \sprintf(
            'https://aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:predict',
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
        $client->request(new Model($modelName), 'test');
    }

    public function testItSendsCorrectContentTypeHeader()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                self::assertContains('Content-Type: application/json', $options['headers']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }

    public function testItConvertsStringPayloadToInstances()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('instances', $body);
                self::assertCount(1, $body['instances']);
                self::assertSame('Hello, World!', $body['instances'][0]['content']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-embedding-001'), 'Hello, World!');
    }

    public function testItConvertsArrayPayloadToMultipleInstances()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('instances', $body);
                self::assertCount(3, $body['instances']);
                self::assertSame('text1', $body['instances'][0]['content']);
                self::assertSame('text2', $body['instances'][1]['content']);
                self::assertSame('text3', $body['instances'][2]['content']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-embedding-001'), ['text1', 'text2', 'text3']);
    }

    public function testItUsesDefaultTaskTypeRetrievalQuery()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                $body = json_decode($options['body'], true);

                self::assertSame(TaskType::RETRIEVAL_QUERY, $body['instances'][0]['task_type']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-embedding-001'), 'test');
    }

    #[TestWith([TaskType::CLASSIFICATION])]
    #[TestWith([TaskType::CLUSTERING])]
    #[TestWith([TaskType::RETRIEVAL_DOCUMENT])]
    #[TestWith([TaskType::RETRIEVAL_QUERY])]
    #[TestWith([TaskType::QUESTION_ANSWERING])]
    #[TestWith([TaskType::FACT_VERIFICATION])]
    #[TestWith([TaskType::CODE_RETRIEVAL_QUERY])]
    #[TestWith([TaskType::SEMANTIC_SIMILARITY])]
    public function testItUsesTaskTypeFromModelOptions(string $taskType)
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use ($taskType): HttpResponse {
                $body = json_decode($options['body'], true);

                self::assertSame($taskType, $body['instances'][0]['task_type']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $model = new Model('gemini-embedding-001', options: ['task_type' => $taskType]);
        $client->request($model, 'test');
    }

    public function testItPassesTitleFromOptions()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                $body = json_decode($options['body'], true);

                self::assertSame('My Document Title', $body['instances'][0]['title']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-embedding-001'), 'test', ['title' => 'My Document Title']);
    }

    public function testItPassesTitleAsNullWhenNotProvided()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('title', $body['instances'][0]);
                self::assertNull($body['instances'][0]['title']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $client->request(new Model('gemini-embedding-001'), 'test');
    }

    public function testItPassesOutputDimensionalityFromModelOptions()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('outputDimensionality', $body);
                self::assertSame(768, $body['outputDimensionality']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $model = new Model('gemini-embedding-001', options: ['outputDimensionality' => 768]);
        $client->request($model, 'test');
    }

    public function testItDoesNotIncludeTaskTypeInTopLevelPayload()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                $body = json_decode($options['body'], true);

                // task_type should only be in instances, not at top level
                self::assertArrayNotHasKey('task_type', $body);
                // But it should be in each instance
                self::assertArrayHasKey('task_type', $body['instances'][0]);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $model = new Model('gemini-embedding-001', options: ['task_type' => TaskType::CLASSIFICATION]);
        $client->request($model, 'test');
    }

    public function testItMergesModelOptionsWithPayload()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                $body = json_decode($options['body'], true);

                self::assertArrayHasKey('instances', $body);
                self::assertArrayHasKey('outputDimensionality', $body);
                self::assertSame(1536, $body['outputDimensionality']);

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $model = new Model('gemini-embedding-001', options: [
            'outputDimensionality' => 1536,
            'task_type' => TaskType::RETRIEVAL_DOCUMENT,
        ]);
        $client->request($model, 'test');
    }

    public function testItHandlesMultipleTextsWithSameTitleAndTaskType()
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): HttpResponse {
                $body = json_decode($options['body'], true);

                self::assertCount(2, $body['instances']);

                foreach ($body['instances'] as $instance) {
                    self::assertSame('Document Title', $instance['title']);
                    self::assertSame(TaskType::RETRIEVAL_DOCUMENT, $instance['task_type']);
                }

                return new JsonMockResponse([]);
            }
        );

        $client = new ModelClient($httpClient, 'global', 'test');
        $model = new Model('gemini-embedding-001', options: ['task_type' => TaskType::RETRIEVAL_DOCUMENT]);
        $client->request($model, ['text1', 'text2'], ['title' => 'Document Title']);
    }

    public function testItReturnsMultipleEmbeddings()
    {
        $expectedResponse = [
            'predictions' => [
                ['embeddings' => ['values' => [0.1, 0.2, 0.3]]],
                ['embeddings' => ['values' => [0.4, 0.5, 0.6]]],
                ['embeddings' => ['values' => [0.7, 0.8, 0.9]]],
            ],
        ];

        $httpClient = new MockHttpClient(new JsonMockResponse($expectedResponse));

        $client = new ModelClient($httpClient, 'global', 'test');
        $result = $client->request(new Model('gemini-embedding-001'), ['text1', 'text2', 'text3']);

        $this->assertSame($expectedResponse, $result->getData());
    }
}

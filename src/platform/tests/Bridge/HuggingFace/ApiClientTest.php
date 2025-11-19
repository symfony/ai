<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\HuggingFace;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\ApiClient;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ApiClientTest extends TestCase
{
    #[TestDox('Returns array of Model objects when API responds with model data')]
    public function testModelsWithProviderAndTask()
    {
        $responseData = [
            ['id' => 'model-1'],
            ['id' => 'model-2'],
            ['id' => 'model-3'],
        ];

        $httpClient = new MockHttpClient(new JsonMockResponse($responseData));
        $apiClient = new ApiClient($httpClient);

        $models = $apiClient->getModels('test-provider', 'text-generation');

        $this->assertCount(3, $models);
        $this->assertInstanceOf(Model::class, $models[0]);
        $this->assertSame('model-1', $models[0]->getName());
        $this->assertSame('model-2', $models[1]->getName());
        $this->assertSame('model-3', $models[2]->getName());
    }

    #[TestDox('Handles null provider and task parameters correctly')]
    public function testModelsWithNullProviderAndTask()
    {
        $responseData = [
            ['id' => 'model-1'],
            ['id' => 'model-2'],
        ];

        $httpClient = new MockHttpClient(new JsonMockResponse($responseData));
        $apiClient = new ApiClient($httpClient);

        $models = $apiClient->getModels();

        $this->assertCount(2, $models);
        $this->assertInstanceOf(Model::class, $models[0]);
        $this->assertSame('model-1', $models[0]->getName());
        $this->assertSame('model-2', $models[1]->getName());
    }

    #[TestDox('Returns empty array when API responds with no models')]
    public function testModelsWithEmptyResponse()
    {
        $responseData = [];

        $httpClient = new MockHttpClient(new JsonMockResponse($responseData));
        $apiClient = new ApiClient($httpClient);

        $models = $apiClient->getModels('test-provider', 'text-generation');

        $this->assertCount(0, $models);
    }

    #[TestDox('Sends correct HTTP request with provider and task parameters')]
    public function testModelsRequestParameters()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertStringStartsWith('https://huggingface.co/api/models', $url);
            $this->assertArrayHasKey('query', $options);
            $this->assertSame('test-provider', $options['query']['inference_provider']);
            $this->assertSame('text-generation', $options['query']['pipeline_tag']);

            return new JsonMockResponse([]);
        });

        $apiClient = new ApiClient($httpClient);
        $apiClient->getModels('test-provider', 'text-generation');
    }

    #[TestDox('Sends correct HTTP request with null provider and task parameters')]
    public function testModelsRequestParametersWithNullValues()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertStringStartsWith('https://huggingface.co/api/models', $url);
            $this->assertArrayHasKey('query', $options);
            $this->assertNull($options['query']['inference_provider']);
            $this->assertNull($options['query']['pipeline_tag']);

            return new JsonMockResponse([]);
        });

        $apiClient = new ApiClient($httpClient);
        $apiClient->getModels();
    }
}

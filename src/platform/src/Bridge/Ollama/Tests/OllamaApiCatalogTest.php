<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Decision;
use Symfony\AI\Platform\Bridge\Ollama\ModelCatalog;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OllamaApiCatalogTest extends TestCase
{
    public function testModelCatalogCanReturnModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'capabilities' => ['completion'],
            ]),
        ], 'http://127.0.0.1:11434');

        $modelCatalog = new ModelCatalog($httpClient);

        $model = $modelCatalog->getModel('foo');

        $this->assertSame('foo', $model->getName());
        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_STRUCTURED,
        ], $model->getCapabilities());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogKeepsTheOptionsOutOfTheModelName()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('http://127.0.0.1:11434/api/show', $url);
            $this->assertSame(['model' => 'qwen3:8b'], json_decode($options['body'], true));

            return new JsonMockResponse(['capabilities' => ['completion', 'tools', 'thinking']]);
        }, 'http://127.0.0.1:11434');

        $model = (new ModelCatalog($httpClient))->getModel('qwen3:8b?think=false&temperature=0.2');

        $this->assertSame('qwen3:8b', $model->getName());
        $this->assertSame(['think' => false, 'temperature' => 0.2], $model->getOptions());
    }

    public function testModelCatalogReturnsDecisionModelWithoutCapabilities()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'capabilities' => ['decision', 'tools', 'thinking', 'completion'],
            ]),
        ], 'http://127.0.0.1:11434');

        $model = (new ModelCatalog($httpClient))->getModel('nimble');

        $this->assertInstanceOf(Decision::class, $model);
        $this->assertSame('nimble', $model->getName());
        $this->assertSame([], $model->getCapabilities());
    }

    public function testModelCatalogPreservesVisionForDecisionModels()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['capabilities' => ['decision', 'vision', 'completion', 'tools']]),
        ], 'http://127.0.0.1:11434');

        $model = (new ModelCatalog($httpClient))->getModel('clef-flash');

        $this->assertInstanceOf(Decision::class, $model);
        $this->assertSame([Capability::INPUT_IMAGE], $model->getCapabilities());
    }

    public function testModelCatalogCanReturnDecisionModelsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    ['name' => 'nimble', 'details' => []],
                    ['name' => 'llama3', 'details' => []],
                ],
            ]),
            new JsonMockResponse([
                'capabilities' => ['decision', 'tools', 'thinking', 'completion'],
            ]),
            new JsonMockResponse([
                'capabilities' => ['completion'],
            ]),
        ], 'http://127.0.0.1:11434');

        $models = (new ModelCatalog($httpClient))->getModels();

        $this->assertSame(['class' => Decision::class, 'capabilities' => []], $models['nimble']);
        $this->assertSame(Ollama::class, $models['llama3']['class']);
    }

    public function testModelCatalogStillRejectsUnknownCapabilities()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'capabilities' => ['completion', 'unknown'],
            ]),
        ], 'http://127.0.0.1:11434');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "unknown" capability is not supported');

        (new ModelCatalog($httpClient))->getModel('foo');
    }

    public function testModelCatalogCanReturnEmbeddingModelsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    [
                        'name' => 'bge-m3',
                        'details' => [],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'capabilities' => ['embedding'],
            ]),
        ], 'http://127.0.0.1:11434');

        $modelCatalog = new ModelCatalog($httpClient);

        $models = $modelCatalog->getModels();

        $this->assertCount(1, $models);
        $this->assertArrayHasKey('bge-m3', $models);

        $model = $models['bge-m3'];
        $this->assertSame(Ollama::class, $model['class']);
        $this->assertCount(1, $model['capabilities']);
        $this->assertSame([
            Capability::EMBEDDINGS,
        ], $model['capabilities']);
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    [
                        'name' => 'gemma3',
                        'details' => [],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'capabilities' => ['completion'],
            ]),
        ], 'http://127.0.0.1:11434');

        $modelCatalog = new ModelCatalog($httpClient);

        $models = $modelCatalog->getModels();

        $this->assertCount(1, $models);
        $this->assertArrayHasKey('gemma3', $models);

        $model = $models['gemma3'];
        $this->assertSame(Ollama::class, $model['class']);
        $this->assertCount(2, $model['capabilities']);
        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_STRUCTURED,
        ], $model['capabilities']);
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnAudioModelsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'capabilities' => ['completion', 'audio'],
            ]),
        ], 'http://127.0.0.1:11434');

        $modelCatalog = new ModelCatalog($httpClient);

        $model = $modelCatalog->getModel('gemma4');

        $this->assertSame('gemma4', $model->getName());
        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_STRUCTURED,
        ], $model->getCapabilities());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnInsertModelsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    [
                        'name' => 'qwen2.5-coder',
                        'details' => [],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'capabilities' => ['insert'],
            ]),
        ], 'http://127.0.0.1:11434');

        $modelCatalog = new ModelCatalog($httpClient);

        $models = $modelCatalog->getModels();

        $this->assertCount(1, $models);
        $this->assertArrayHasKey('qwen2.5-coder', $models);

        $model = $models['qwen2.5-coder'];
        $this->assertSame(Ollama::class, $model['class']);
        $this->assertCount(2, $model['capabilities']);
        $this->assertSame([
            Capability::FILL_IN_THE_MIDDLE,
            Capability::OUTPUT_STRUCTURED,
        ], $model['capabilities']);
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    #[TestDox('Returns empty array when Ollama has no models')]
    public function testGetModelsReturnsEmptyArrayWhenNoModels()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse(['models' => []]),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->assertSame([], $modelCatalog->getModels());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    #[TestDox('Throws RuntimeException when Ollama API is unreachable for getModel')]
    public function testGetModelThrowsRuntimeExceptionOnTransportError()
    {
        $httpClient = new MockHttpClient(
            new MockResponse('', ['error' => 'Connection refused']),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to the Ollama API:');

        $modelCatalog->getModel('some-model');
    }

    #[TestDox('Throws RuntimeException when Ollama API is unreachable for getModels')]
    public function testGetModelsThrowsRuntimeExceptionOnTransportError()
    {
        $httpClient = new MockHttpClient(
            new MockResponse('', ['error' => 'Connection refused']),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to the Ollama API:');

        $modelCatalog->getModels();
    }

    #[TestDox('Throws ModelNotFoundException when API returns 404')]
    public function testGetModelThrowsModelNotFoundExceptionOn404()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse(['error' => "model 'nonexistent' not found"], ['http_code' => 404]),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model "nonexistent" not found: "model \'nonexistent\' not found".');

        $modelCatalog->getModel('nonexistent');
    }

    #[TestDox('Throws RuntimeException when API returns 400 with "not found" in message')]
    public function testGetModelThrowsRuntimeExceptionOn400WithNotFoundMessage()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse(['error' => "model 'kimi-v2.5:cloud' not found"], ['http_code' => 400]),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot load model information from the Ollama API (Status code: 400): "model \'kimi-v2.5:cloud\' not found".');

        $modelCatalog->getModel('kimi-v2.5:cloud');
    }

    #[TestDox('Throws RuntimeException for other API errors with JSON error')]
    public function testGetModelThrowsRuntimeExceptionForOtherJsonErrors()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse(['error' => 'Internal server error'], ['http_code' => 500]),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot load model information from the Ollama API (Status code: 500): "Internal server error".');

        $modelCatalog->getModel('some-model');
    }

    #[TestDox('Throws RuntimeException for API errors with plain text response')]
    public function testGetModelThrowsRuntimeExceptionForPlainTextErrors()
    {
        $httpClient = new MockHttpClient(
            new MockResponse('Service temporarily unavailable', ['http_code' => 503]),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot load model information from the Ollama API (Status code: 503): "Service temporarily unavailable".');

        $modelCatalog->getModel('some-model');
    }

    #[TestDox('Throws RuntimeException for API errors with empty response body')]
    public function testGetModelThrowsRuntimeExceptionForEmptyResponse()
    {
        $httpClient = new MockHttpClient(
            new MockResponse('', ['http_code' => 502]),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot load model information from the Ollama API (Status code: 502)');

        $modelCatalog->getModel('some-model');
    }

    #[TestDox('Throws RuntimeException when getModels API returns error with JSON')]
    public function testGetModelsThrowsRuntimeExceptionForJsonErrors()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse(['error' => 'Unable to list models'], ['http_code' => 500]),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot retrieve models from the Ollama API (Status code: 500): "Unable to list models".');

        $modelCatalog->getModels();
    }

    #[TestDox('Throws RuntimeException when getModels API returns plain text error')]
    public function testGetModelsThrowsRuntimeExceptionForPlainTextErrors()
    {
        $httpClient = new MockHttpClient(
            new MockResponse('Connection refused', ['http_code' => 503]),
            'http://127.0.0.1:11434',
        );

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot retrieve models from the Ollama API (Status code: 503): "Connection refused".');

        $modelCatalog->getModels();
    }
}

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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\ModelCatalog;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Capability;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelCatalogTest extends TestCase
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
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STRUCTURED,
            Capability::OUTPUT_IMAGE,
        ], $model->getCapabilities());
        $this->assertSame(1, $httpClient->getRequestsCount());
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
        $this->assertCount(2, $model['capabilities']);
        $this->assertSame([
            Capability::EMBEDDINGS,
            Capability::OUTPUT_EMBEDDINGS,
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
        $this->assertCount(5, $model['capabilities']);
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STRUCTURED,
            Capability::OUTPUT_IMAGE,
        ], $model['capabilities']);
        $this->assertSame(2, $httpClient->getRequestsCount());
    }
}

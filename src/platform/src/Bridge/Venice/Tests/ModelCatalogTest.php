<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelCatalogTest extends TestCase
{
    public function testModelCatalogCannotReturnModelFromApiWhenUndefined()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['data' => []]),
        ]);

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" cannot be retrieved from the API.');
        $this->expectExceptionCode(0);
        $modelCatalog->getModel('foo');
    }

    public function testModelCatalogCannotReturnUnsupportedModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'llama-3.2-3b',
                        'model_spec' => [
                            'capabilities' => [
                                'optimizedForCode' => true,
                                'quantization' => 'fp16',
                                'supportsFunctionCalling' => true,
                                'supportsReasoning' => false,
                                'supportsVision' => false,
                                'supportsWebSearch' => true,
                            ],
                        ],
                        'object' => 'model',
                        'owned_by' => 'venice.ai',
                        'type' => 'text',
                    ],
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'foo',
                        'model_spec' => [
                            'capabilities' => [
                                'optimizedForCode' => false,
                                'quantization' => 'fp16',
                                'supportsFunctionCalling' => false,
                                'supportsReasoning' => false,
                                'supportsVision' => false,
                                'supportsWebSearch' => false,
                            ],
                        ],
                        'object' => 'model',
                        'owned_by' => 'venice.ai',
                        'type' => 'text',
                    ],
                ],
                'object' => 'list',
                'type' => 'all',
            ]),
        ]);

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" is not supported, please check the Venice API.');
        $this->expectExceptionCode(0);
        $modelCatalog->getModel('foo');
    }

    public function testModelCatalogCanReturnAsrModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'nvidia/parakeet-tdt-0.6b-v3',
                        'model_spec' => [
                            'pricing' => [
                                'per_audio_second' => [
                                    'usd' => 0.0001,
                                    'diem' => 0.0001
                                ],
                            ],
                        ],
                        'name' => 'Parakeet ASR',
                        'modelSource' => 'https://huggingface.co/nvidia/parakeet-tdt-0.6b-v3',
                        'offline' => false,
                        'privacy' => 'private',
                        'object' => 'model',
                        'owned_by' => 'venice.ai',
                        'type' => 'asr',
                    ],
                ],
                'object' => 'list',
                'type' => 'all',
            ]),
        ]);

        $modelCatalog = new ModelCatalog($httpClient);

        $model = $modelCatalog->getModel('nvidia/parakeet-tdt-0.6b-v3');

        $this->assertSame('nvidia/parakeet-tdt-0.6b-v3', $model->getName());
        $this->assertSame([
            Capability::SPEECH_RECOGNITION,
            Capability::INPUT_TEXT,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnEmbeddingModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'text-embedding-bge-m3',
                        'model_spec' => [
                            'pricing' => [
                                'input' => [
                                    'usd' => 0.15,
                                    'diem' => 0.15
                                ],
                                'output' => [
                                    'usd' => 0.6,
                                    'diem' => 0.6
                                ],
                            ],
                        ],
                        'name' => 'BGE-3',
                        'modelSource' => 'https://huggingface.co/BAAI/bge-m3',
                        'offline' => false,
                        'privacy' => 'private',
                        'object' => 'model',
                        'owned_by' => 'venice.ai',
                        'type' => 'embedding',
                    ],
                ],
                'object' => 'list',
                'type' => 'all',
            ]),
        ]);

        $modelCatalog = new ModelCatalog($httpClient);

        $model = $modelCatalog->getModel('text-embedding-bge-m3');

        $this->assertSame('text-embedding-bge-m3', $model->getName());
        $this->assertSame([
            Capability::EMBEDDINGS,
            Capability::INPUT_TEXT,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnImageModelFromApi()
    {

    }
}

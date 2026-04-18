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
        $this->expectExceptionMessage('No models available in the Venice catalog.');
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
                        'model_spec' => [],
                        'object' => 'model',
                        'owned_by' => 'venice.ai',
                        'type' => 'unsupported_type',
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
                                    'diem' => 0.0001,
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
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
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
                                    'diem' => 0.15,
                                ],
                                'output' => [
                                    'usd' => 0.6,
                                    'diem' => 0.6,
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
            Capability::OUTPUT_EMBEDDINGS,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnImageModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'fluently-xl',
                        'model_spec' => [
                            'pricing' => [
                                'per_image' => [
                                    'usd' => 0.04,
                                    'diem' => 0.04,
                                ],
                            ],
                        ],
                        'name' => 'Fluently XL',
                        'offline' => false,
                        'privacy' => 'private',
                        'object' => 'model',
                        'owned_by' => 'venice.ai',
                        'type' => 'image',
                    ],
                ],
                'object' => 'list',
                'type' => 'all',
            ]),
        ]);

        $modelCatalog = new ModelCatalog($httpClient);

        $model = $modelCatalog->getModel('fluently-xl');

        $this->assertSame('fluently-xl', $model->getName());
        $this->assertSame([
            Capability::TEXT_TO_IMAGE,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_IMAGE,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnPlainTextModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'venice-uncensored',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => false,
                                'supportsReasoning' => false,
                                'supportsVision' => false,
                                'supportsAudioInput' => false,
                                'supportsVideoInput' => false,
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

        $model = $modelCatalog->getModel('venice-uncensored');

        $this->assertSame('venice-uncensored', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelWithFunctionCallingFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'mistral-small-3-2-24b-instruct',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => true,
                                'supportsReasoning' => false,
                                'supportsVision' => false,
                                'supportsAudioInput' => false,
                                'supportsVideoInput' => false,
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

        $model = $modelCatalog->getModel('mistral-small-3-2-24b-instruct');

        $this->assertSame('mistral-small-3-2-24b-instruct', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::TOOL_CALLING,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelWithReasoningFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'aion-labs.aion-2-0',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => false,
                                'supportsReasoning' => true,
                                'supportsVision' => false,
                                'supportsAudioInput' => false,
                                'supportsVideoInput' => false,
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

        $model = $modelCatalog->getModel('aion-labs.aion-2-0');

        $this->assertSame('aion-labs.aion-2-0', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::THINKING,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelWithFunctionCallingAndReasoningFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'deepseek-v3.2',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => true,
                                'supportsReasoning' => true,
                                'supportsVision' => false,
                                'supportsAudioInput' => false,
                                'supportsVideoInput' => false,
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

        $model = $modelCatalog->getModel('deepseek-v3.2');

        $this->assertSame('deepseek-v3.2', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::TOOL_CALLING,
            Capability::THINKING,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelWithFunctionCallingAndVisionFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'google-gemma-3-27b-it',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => true,
                                'supportsReasoning' => false,
                                'supportsVision' => true,
                                'supportsAudioInput' => false,
                                'supportsVideoInput' => false,
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

        $model = $modelCatalog->getModel('google-gemma-3-27b-it');

        $this->assertSame('google-gemma-3-27b-it', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelWithVisionFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'e2ee-qwen3-vl-30b-a3b-p',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => false,
                                'supportsReasoning' => false,
                                'supportsVision' => true,
                                'supportsAudioInput' => false,
                                'supportsVideoInput' => false,
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

        $model = $modelCatalog->getModel('e2ee-qwen3-vl-30b-a3b-p');

        $this->assertSame('e2ee-qwen3-vl-30b-a3b-p', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::INPUT_IMAGE,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelWithReasoningAndVisionFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'grok-4-20-multi-agent-beta',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => false,
                                'supportsReasoning' => true,
                                'supportsVision' => true,
                                'supportsAudioInput' => false,
                                'supportsVideoInput' => false,
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

        $model = $modelCatalog->getModel('grok-4-20-multi-agent-beta');

        $this->assertSame('grok-4-20-multi-agent-beta', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::THINKING,
            Capability::INPUT_IMAGE,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelWithFunctionCallingReasoningAndVisionFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'claude-opus-4-6',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => true,
                                'supportsReasoning' => true,
                                'supportsVision' => true,
                                'supportsAudioInput' => false,
                                'supportsVideoInput' => false,
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

        $model = $modelCatalog->getModel('claude-opus-4-6');

        $this->assertSame('claude-opus-4-6', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::TOOL_CALLING,
            Capability::THINKING,
            Capability::INPUT_IMAGE,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelWithFunctionCallingReasoningVisionAndVideoFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'qwen3-5-35b-a3b',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => true,
                                'supportsReasoning' => true,
                                'supportsVision' => true,
                                'supportsAudioInput' => false,
                                'supportsVideoInput' => true,
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

        $model = $modelCatalog->getModel('qwen3-5-35b-a3b');

        $this->assertSame('qwen3-5-35b-a3b', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::TOOL_CALLING,
            Capability::THINKING,
            Capability::INPUT_IMAGE,
            Capability::INPUT_VIDEO,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTextModelWithAllCapabilitiesFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'gemini-3-1-pro-preview',
                        'model_spec' => [
                            'capabilities' => [
                                'supportsFunctionCalling' => true,
                                'supportsReasoning' => true,
                                'supportsVision' => true,
                                'supportsAudioInput' => true,
                                'supportsVideoInput' => true,
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

        $model = $modelCatalog->getModel('gemini-3-1-pro-preview');

        $this->assertSame('gemini-3-1-pro-preview', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::TOOL_CALLING,
            Capability::THINKING,
            Capability::INPUT_IMAGE,
            Capability::INPUT_AUDIO,
            Capability::INPUT_VIDEO,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnTtsModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'tts-kokoro-v1',
                        'model_spec' => [
                            'pricing' => [
                                'per_audio_second' => [
                                    'usd' => 0.0001,
                                    'diem' => 0.0001,
                                ],
                            ],
                        ],
                        'object' => 'model',
                        'owned_by' => 'venice.ai',
                        'type' => 'tts',
                    ],
                ],
                'object' => 'list',
                'type' => 'all',
            ]),
        ]);

        $modelCatalog = new ModelCatalog($httpClient);

        $model = $modelCatalog->getModel('tts-kokoro-v1');

        $this->assertSame('tts-kokoro-v1', $model->getName());
        $this->assertSame([
            Capability::TEXT_TO_SPEECH,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_AUDIO,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnVideoModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'createdAt' => (new \DateTimeImmutable())->getTimestamp(),
                        'id' => 'wan-2-1-fast',
                        'model_spec' => [
                            'constraints' => [
                                'model_type' => 'image-to-video',
                            ],
                            'pricing' => [
                                'per_video' => [
                                    'usd' => 0.1,
                                    'diem' => 0.1,
                                ],
                            ],
                        ],
                        'object' => 'model',
                        'owned_by' => 'venice.ai',
                        'type' => 'video',
                    ],
                ],
                'object' => 'list',
                'type' => 'all',
            ]),
        ]);

        $modelCatalog = new ModelCatalog($httpClient);

        $model = $modelCatalog->getModel('wan-2-1-fast');

        $this->assertSame('wan-2-1-fast', $model->getName());
        $this->assertSame([
            Capability::IMAGE_TO_VIDEO,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_VIDEO,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}

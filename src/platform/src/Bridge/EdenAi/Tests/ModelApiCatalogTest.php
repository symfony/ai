<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis;
use Symfony\AI\Platform\Bridge\EdenAi\ModelApiCatalog;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelApiCatalogTest extends TestCase
{
    public function testItDiscoversLanguageModelsWithTheirCapabilities()
    {
        $catalog = new ModelApiCatalog($this->httpClient());

        $model = $catalog->getModel('anthropic/claude-opus-5');

        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertTrue($model->supports(Capability::INPUT_MESSAGES));
        $this->assertTrue($model->supports(Capability::OUTPUT_STREAMING));
        $this->assertTrue($model->supports(Capability::TOOL_CALLING));
        $this->assertTrue($model->supports(Capability::OUTPUT_STRUCTURED));
        $this->assertTrue($model->supports(Capability::INPUT_IMAGE));
        // Eden AI advertises document input as the "file" modality.
        $this->assertTrue($model->supports(Capability::INPUT_PDF));
        $this->assertFalse($model->supports(Capability::INPUT_AUDIO));
    }

    public function testItOmitsCapabilitiesTheGatewayDoesNotAdvertise()
    {
        $catalog = new ModelApiCatalog($this->httpClient());

        $model = $catalog->getModel('deepseek/deepseek-reasoner');

        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
        $this->assertFalse($model->supports(Capability::TOOL_CALLING));
        $this->assertFalse($model->supports(Capability::INPUT_IMAGE));
    }

    public function testItMapsTheRemainingModalities()
    {
        $catalog = new ModelApiCatalog($this->httpClient());

        $model = $catalog->getModel('google/gemini-2.5-pro');

        $this->assertTrue($model->supports(Capability::INPUT_AUDIO));
        $this->assertTrue($model->supports(Capability::INPUT_VIDEO));
    }

    public function testItDiscoversEmbeddingModels()
    {
        $catalog = new ModelApiCatalog($this->httpClient());

        $model = $catalog->getModel('openai/text-embedding-3-small');

        $this->assertInstanceOf(EmbeddingsModel::class, $model);
        $this->assertTrue($model->supports(Capability::EMBEDDINGS));
    }

    public function testItDiscoversExpertModelsOfSupportedSubfeatures()
    {
        $catalog = new ModelApiCatalog($this->httpClient());

        $this->assertInstanceOf(Tts::class, $catalog->getModel('audio/tts/elevenlabs/eleven_multilingual_v2'));
        $this->assertInstanceOf(ImageAnalysis::class, $catalog->getModel('image/logo_detection/microsoft'));
    }

    /**
     * A subfeature without a result converter must stay hidden rather than fail later.
     */
    public function testItHidesExpertSubfeaturesTheBridgeCannotConvert()
    {
        $catalog = new ModelApiCatalog($this->httpClient());

        $this->expectException(ModelNotFoundException::class);

        $catalog->getModel('translation/automatic_translation/google');
    }

    public function testItDiscoversOnlyOnce()
    {
        $httpClient = $this->httpClient();
        $catalog = new ModelApiCatalog($httpClient);

        $catalog->getModel('anthropic/claude-opus-5');
        $catalog->getModel('openai/text-embedding-3-small');
        $catalog->getModels();

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testExplicitlyRegisteredModelsWinOverDiscoveredOnes()
    {
        $catalog = new ModelApiCatalog($this->httpClient(), 'https://api.edenai.run', [
            'anthropic/claude-opus-5' => ['class' => CompletionsModel::class, 'capabilities' => [Capability::OUTPUT_TEXT]],
        ]);

        $model = $catalog->getModel('anthropic/claude-opus-5');

        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
        $this->assertFalse($model->supports(Capability::TOOL_CALLING));
    }

    public function testItForwardsTheConfiguredBaseUrl()
    {
        $urls = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): JsonMockResponse {
            $urls[] = $url;

            return new JsonMockResponse(['data' => [], 'features' => []]);
        });

        $catalog = new ModelApiCatalog($httpClient, 'https://gateway.example.com');
        $catalog->getModels();

        $this->assertSame([
            'https://gateway.example.com/v3/models',
            'https://gateway.example.com/v3/embeddings/models',
            'https://gateway.example.com/v3/info',
        ], $urls);
    }

    public function testItReportsADiscoveryFailureAsAPlatformException()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 503]));

        $catalog = new ModelApiCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not discover the Eden AI models from "/v3/models".');

        $catalog->getModels();
    }

    /**
     * Mirrors the payloads of GET /v3/models, /v3/embeddings/models and /v3/info.
     */
    private function httpClient(): MockHttpClient
    {
        return new MockHttpClient([
            new JsonMockResponse(['object' => 'list', 'data' => [
                [
                    'id' => 'anthropic/claude-opus-5',
                    'capabilities' => [
                        'input_modalities' => ['text', 'image', 'file'],
                        'supports_function_calling' => true,
                        'supports_response_schema' => true,
                    ],
                ],
                [
                    'id' => 'deepseek/deepseek-reasoner',
                    'capabilities' => [
                        'input_modalities' => ['text'],
                        'supports_function_calling' => false,
                        'supports_response_schema' => false,
                    ],
                ],
                [
                    'id' => 'google/gemini-2.5-pro',
                    'capabilities' => [
                        'input_modalities' => ['text', 'image', 'file', 'audio', 'video'],
                        'supports_function_calling' => true,
                        'supports_response_schema' => true,
                    ],
                ],
            ]]),
            new JsonMockResponse(['object' => 'list', 'data' => [
                ['id' => 'openai/text-embedding-3-small'],
            ]]),
            new JsonMockResponse(['features' => [
                [
                    'name' => 'audio',
                    'subfeatures' => [
                        [
                            'name' => 'tts',
                            'mode' => 'sync',
                            'models' => [
                                ['model' => 'audio/tts/elevenlabs/eleven_multilingual_v2'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'image',
                    'subfeatures' => [
                        [
                            'name' => 'logo_detection',
                            'mode' => 'sync',
                            'models' => [
                                ['model' => 'image/logo_detection/microsoft'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'translation',
                    'subfeatures' => [
                        [
                            'name' => 'automatic_translation',
                            'mode' => 'sync',
                            'models' => [
                                ['model' => 'translation/automatic_translation/google'],
                            ],
                        ],
                    ],
                ],
            ]]),
        ]);
    }
}

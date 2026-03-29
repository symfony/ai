<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\GeminiApiCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class GeminiApiCatalogTest extends TestCase
{
    public function testGetModelThrowsWhenModelNotFound()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);

            return new JsonMockResponse(['models' => []]);
        });

        $catalog = new GeminiApiCatalog($httpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model "foo" not found, please check the Gemini API.');

        $catalog->getModel('foo');
    }

    public function testGetModelThrowsWhenModelNotSupported()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/unsupported-model',
                    'version' => '1.0',
                    'description' => 'An unsupported model',
                    'inputTokenLimit' => 1000,
                    'outputTokenLimit' => 500,
                    'supportedGenerationMethods' => ['countTokens'],
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "unsupported-model" is not supported, please check the Gemini API.');

        $catalog->getModel('unsupported-model');
    }

    public function testGetModelReturnsContentGenerationModel()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.0-flash',
                    'version' => '2.0',
                    'description' => 'Fast and versatile model',
                    'inputTokenLimit' => 1048576,
                    'outputTokenLimit' => 8192,
                    'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.0-flash');

        $this->assertInstanceOf(Gemini::class, $model);
        $this->assertSame('gemini-2.0-flash', $model->getName());
        $this->assertSame('2.0', $model->getVersion());
        $this->assertSame(1048576, $model->getInputTokenLimit());
        $this->assertSame(8192, $model->getOutputTokenLimit());
        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::INPUT_AUDIO,
            Capability::INPUT_PDF,
            Capability::INPUT_VIDEO,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::TOOL_CALLING,
        ], $model->getCapabilities());
    }

    public function testGetModelReturnsTtsModel()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.5-flash-preview-tts',
                    'version' => '2.5',
                    'description' => 'TTS model',
                    'inputTokenLimit' => 8192,
                    'outputTokenLimit' => 8192,
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-flash-preview-tts');

        $this->assertInstanceOf(Gemini::class, $model);
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_AUDIO,
            Capability::TEXT_TO_SPEECH,
        ], $model->getCapabilities());
    }

    public function testGetModelReturnsNativeAudioModel()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.5-flash-native-audio-preview-12-2025',
                    'version' => '2.5',
                    'description' => 'Native audio model',
                    'inputTokenLimit' => 8192,
                    'outputTokenLimit' => 8192,
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-flash-native-audio-preview-12-2025');

        $this->assertInstanceOf(Gemini::class, $model);
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_AUDIO,
            Capability::TEXT_TO_SPEECH,
        ], $model->getCapabilities());
    }

    public function testGetModelReturnsImageModel()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.5-flash-image',
                    'version' => '2.5',
                    'description' => 'Image generation model',
                    'inputTokenLimit' => 8192,
                    'outputTokenLimit' => 8192,
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-flash-image');

        $this->assertInstanceOf(Gemini::class, $model);
        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_IMAGE,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STRUCTURED,
        ], $model->getCapabilities());
    }

    public function testGetModelReturnsEmbeddingModel()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-embedding-001',
                    'version' => '001',
                    'description' => 'Embedding model',
                    'inputTokenLimit' => 2048,
                    'outputTokenLimit' => 0,
                    'supportedGenerationMethods' => ['embedContent', 'countTokens'],
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);
        $model = $catalog->getModel('gemini-embedding-001');

        $this->assertInstanceOf(Gemini::class, $model);
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::EMBEDDINGS,
        ], $model->getCapabilities());
    }

    public function testGetModelReturnsModelWithThinking()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.5-pro',
                    'version' => '2.5',
                    'description' => 'Most intelligent model',
                    'inputTokenLimit' => 1048576,
                    'outputTokenLimit' => 65536,
                    'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                    'thinking' => true,
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-pro');

        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::INPUT_AUDIO,
            Capability::INPUT_PDF,
            Capability::INPUT_VIDEO,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::TOOL_CALLING,
            Capability::THINKING,
        ], $model->getCapabilities());
    }

    public function testGetModelReturnsModelWithCache()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.5-flash',
                    'version' => '2.5',
                    'description' => 'Fast model with caching',
                    'inputTokenLimit' => 1048576,
                    'outputTokenLimit' => 65536,
                    'supportedGenerationMethods' => ['generateContent', 'countTokens', 'createCachedContent'],
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-flash');

        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::INPUT_AUDIO,
            Capability::INPUT_PDF,
            Capability::INPUT_VIDEO,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::TOOL_CALLING,
            Capability::CACHE,
        ], $model->getCapabilities());
    }

    public function testGetModelReturnsModelWithThinkingAndCache()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.5-pro',
                    'version' => '2.5',
                    'description' => 'Most intelligent model',
                    'inputTokenLimit' => 1048576,
                    'outputTokenLimit' => 65536,
                    'supportedGenerationMethods' => ['generateContent', 'countTokens', 'createCachedContent'],
                    'thinking' => true,
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-pro');

        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::INPUT_AUDIO,
            Capability::INPUT_PDF,
            Capability::INPUT_VIDEO,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::TOOL_CALLING,
            Capability::THINKING,
            Capability::CACHE,
        ], $model->getCapabilities());
    }

    public function testGetModelsReturnsEmptyArrayWhenNoModels()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);

        $this->assertSame([], $catalog->getModels());
    }

    public function testGetModelsReturnsAllModels()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.5-pro',
                    'version' => '2.5',
                    'description' => 'Most intelligent model',
                    'inputTokenLimit' => 1048576,
                    'outputTokenLimit' => 65536,
                    'supportedGenerationMethods' => ['generateContent', 'countTokens', 'createCachedContent'],
                    'thinking' => true,
                ],
                [
                    'name' => 'models/gemini-2.5-flash-preview-tts',
                    'version' => '2.5',
                    'description' => 'TTS model',
                    'inputTokenLimit' => 8192,
                    'outputTokenLimit' => 8192,
                    'supportedGenerationMethods' => ['generateContent'],
                ],
                [
                    'name' => 'models/gemini-embedding-001',
                    'version' => '001',
                    'description' => 'Embedding model',
                    'inputTokenLimit' => 2048,
                    'outputTokenLimit' => 0,
                    'supportedGenerationMethods' => ['embedContent', 'countTokens'],
                ],
            ],
        ]));

        $catalog = new GeminiApiCatalog($httpClient);
        $models = $catalog->getModels();

        $this->assertCount(3, $models);
        $this->assertArrayHasKey('gemini-2.5-pro', $models);
        $this->assertArrayHasKey('gemini-2.5-flash-preview-tts', $models);
        $this->assertArrayHasKey('gemini-embedding-001', $models);

        $this->assertSame(Gemini::class, $models['gemini-2.5-pro']['class']);
        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::INPUT_AUDIO,
            Capability::INPUT_PDF,
            Capability::INPUT_VIDEO,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::TOOL_CALLING,
            Capability::THINKING,
            Capability::CACHE,
        ], $models['gemini-2.5-pro']['capabilities']);

        $this->assertSame(Gemini::class, $models['gemini-2.5-flash-preview-tts']['class']);
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_AUDIO,
            Capability::TEXT_TO_SPEECH,
        ], $models['gemini-2.5-flash-preview-tts']['capabilities']);

        $this->assertSame(Gemini::class, $models['gemini-embedding-001']['class']);
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::EMBEDDINGS,
        ], $models['gemini-embedding-001']['capabilities']);
    }
}

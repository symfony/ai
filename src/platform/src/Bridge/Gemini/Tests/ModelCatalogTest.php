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
use Symfony\AI\Platform\Bridge\Gemini\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelCatalogTest extends TestCase
{
    public function testGetModelThrowsWhenModelNotFound()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);

            return new JsonMockResponse(['models' => []]);
        });

        $catalog = new ModelCatalog($httpClient);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model "foo" not found in the Gemini API catalog.');

        $catalog->getModel('foo');
    }

    public function testGetModelThrowsWhenModelHasNoSupportedCapabilities()
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

        $catalog = new ModelCatalog($httpClient);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model "unsupported-model" has no supported capabilities exposed by the Gemini API.');

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

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.0-flash');

        $this->assertInstanceOf(Gemini::class, $model);
        $this->assertSame('gemini-2.0-flash', $model->getName());
        $this->assertSame('2.0', $model->getVersion());
        $this->assertSame(1048576, $model->getInputTokenLimit());
        $this->assertSame(8192, $model->getOutputTokenLimit());
        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::INPUT_TEXT,
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

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-flash-preview-tts');

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

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-flash-native-audio-preview-12-2025');

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

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-flash-image');

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

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('gemini-embedding-001');

        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::EMBEDDINGS,
            Capability::OUTPUT_EMBEDDINGS,
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

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-pro');

        $this->assertContains(Capability::THINKING, $model->getCapabilities());
        $this->assertContains(Capability::TOOL_CALLING, $model->getCapabilities());
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

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('gemini-2.5-flash');

        $this->assertContains(Capability::CACHE, $model->getCapabilities());
    }

    public function testGetModelsReturnsEmptyArrayWhenNoModels()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [],
        ]));

        $catalog = new ModelCatalog($httpClient);

        $this->assertSame([], $catalog->getModels());
    }

    public function testGetModelsReturnsAllModels()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.5-pro',
                    'version' => '2.5',
                    'inputTokenLimit' => 1048576,
                    'outputTokenLimit' => 65536,
                    'supportedGenerationMethods' => ['generateContent', 'createCachedContent'],
                    'thinking' => true,
                ],
                [
                    'name' => 'models/gemini-embedding-001',
                    'version' => '001',
                    'inputTokenLimit' => 2048,
                    'outputTokenLimit' => 0,
                    'supportedGenerationMethods' => ['embedContent'],
                ],
            ],
        ]));

        $catalog = new ModelCatalog($httpClient);
        $models = $catalog->getModels();

        $this->assertCount(2, $models);
        $this->assertArrayHasKey('gemini-2.5-pro', $models);
        $this->assertArrayHasKey('gemini-embedding-001', $models);
        $this->assertSame(Gemini::class, $models['gemini-2.5-pro']['class']);
        $this->assertContains(Capability::THINKING, $models['gemini-2.5-pro']['capabilities']);
        $this->assertContains(Capability::CACHE, $models['gemini-2.5-pro']['capabilities']);
    }

    public function testGetModelsHandlesPagination()
    {
        $page1 = new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.5-pro',
                    'version' => '2.5',
                    'inputTokenLimit' => 1048576,
                    'outputTokenLimit' => 65536,
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
            'nextPageToken' => 'page-2-token',
        ]);

        $page2 = new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.0-flash',
                    'version' => '2.0',
                    'inputTokenLimit' => 1048576,
                    'outputTokenLimit' => 8192,
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ]);

        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $page1, $page2) {
            $requests[] = $options['query'] ?? [];

            return 1 === \count($requests) ? $page1 : $page2;
        });

        $catalog = new ModelCatalog($httpClient);
        $models = $catalog->getModels();

        $this->assertCount(2, $models);
        $this->assertArrayHasKey('gemini-2.5-pro', $models);
        $this->assertArrayHasKey('gemini-2.0-flash', $models);
        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertArrayNotHasKey('pageToken', $requests[0]);
        $this->assertSame('page-2-token', $requests[1]['pageToken']);
    }

    public function testGetModelsCachesInMemory()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'models' => [
                [
                    'name' => 'models/gemini-2.0-flash',
                    'version' => '2.0',
                    'inputTokenLimit' => 1048576,
                    'outputTokenLimit' => 8192,
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ]));

        $catalog = new ModelCatalog($httpClient);

        $catalog->getModels();
        $catalog->getModels();
        $catalog->getModel('gemini-2.0-flash');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testGetModelsThrowsRuntimeOnNon200()
    {
        $httpClient = new MockHttpClient(new MockResponse(
            '{"error":{"code":403,"message":"API key not valid","status":"PERMISSION_DENIED"}}',
            ['http_code' => 403],
        ));

        $catalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot retrieve models from the Gemini API (Status code: 403): "API key not valid".');

        $catalog->getModels();
    }

    public function testGetModelsThrowsRuntimeOnTransportError()
    {
        $httpClient = new MockHttpClient(static function (): never {
            throw new TransportException('Connection refused');
        });

        $catalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to the Gemini API: "Connection refused".');

        $catalog->getModels();
    }
}

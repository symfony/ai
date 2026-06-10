<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Together\ModelCatalog;
use Symfony\AI\Platform\Bridge\Together\Together;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalogTest extends TestCase
{
    private const MODELS_FIXTURE = [
        ['id' => 'openai/gpt-oss-120b', 'object' => 'model', 'type' => 'chat'],
        ['id' => 'meta-llama/Llama-Guard-4-12B', 'object' => 'model', 'type' => 'moderation'],
        ['id' => 'black-forest-labs/FLUX.1-schnell', 'object' => 'model', 'type' => 'image'],
        ['id' => 'BAAI/bge-large-en-v1.5', 'object' => 'model', 'type' => 'embedding'],
        ['id' => 'Salesforce/Llama-Rank-V1', 'object' => 'model', 'type' => 'rerank'],
        ['id' => 'mistralai/Mixtral-8x7B-v0.1', 'object' => 'model', 'type' => 'language'],
    ];

    public function testItRetrievesModelsFromTheApi()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $models = $catalog->getModels();

        // 6 dynamic models from the API + 5 statically overlaid audio models.
        $this->assertCount(11, $models);
        $this->assertArrayHasKey('openai/gpt-oss-120b', $models);
        $this->assertSame(Together::class, $models['openai/gpt-oss-120b']['class']);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItMapsChatTypeToCompletionCapabilities()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $model = $catalog->getModel('openai/gpt-oss-120b');

        $this->assertInstanceOf(Together::class, $model);
        $this->assertSame('openai/gpt-oss-120b', $model->getName());
        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::TOOL_CALLING,
        ], $model->getCapabilities());
    }

    public function testItMapsEmbeddingTypeToEmbeddingsCapabilities()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $model = $catalog->getModel('BAAI/bge-large-en-v1.5');

        $this->assertSame([Capability::INPUT_TEXT, Capability::EMBEDDINGS], $model->getCapabilities());
    }

    public function testItMapsImageTypeToImageCapabilities()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $model = $catalog->getModel('black-forest-labs/FLUX.1-schnell');

        $this->assertSame([Capability::INPUT_TEXT, Capability::OUTPUT_IMAGE, Capability::TEXT_TO_IMAGE], $model->getCapabilities());
    }

    public function testItMapsRerankTypeToRerankingCapabilities()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $model = $catalog->getModel('Salesforce/Llama-Rank-V1');

        $this->assertSame([Capability::INPUT_TEXT, Capability::RERANKING], $model->getCapabilities());
    }

    public function testItMapsLanguageTypeToBaseCompletionCapabilities()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $model = $catalog->getModel('mistralai/Mixtral-8x7B-v0.1');

        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
        ], $model->getCapabilities());
    }

    public function testItThrowsWhenModelIsUnknown()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model "unknown/model" not found.');

        $catalog->getModel('unknown/model');
    }

    public function testItThrowsWhenTheApiReturnsAnError()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse([], ['http_code' => 500])], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot retrieve models from the Together API (Status code: 500).');

        $catalog->getModels();
    }

    public function testItIgnoresMalformedEntries()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse([
            ['id' => 'valid/model', 'type' => 'chat'],
            ['object' => 'model'],
            'not-an-array',
            ['id' => 123, 'type' => 'chat'],
        ])], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $models = $catalog->getModels();

        // Only "valid/model" survives from the API payload; the 5 audio overlay models are always present.
        $this->assertArrayHasKey('valid/model', $models);
        $this->assertCount(6, $models);
    }

    public function testItMemoizesTheApiCall()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $catalog->getModels();
        $catalog->getModel('openai/gpt-oss-120b');
        $catalog->getModels();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItOverlaysStaticAudioModels()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], 'https://api.together.xyz');
        $catalog = new ModelCatalog($httpClient);

        $tts = $catalog->getModel('cartesia/sonic');
        $this->assertSame([Capability::TEXT_TO_SPEECH, Capability::OUTPUT_AUDIO], $tts->getCapabilities());

        $stt = $catalog->getModel('openai/whisper-large-v3');
        $this->assertSame([Capability::SPEECH_TO_TEXT, Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT], $stt->getCapabilities());
    }
}

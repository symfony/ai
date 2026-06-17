<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Fireworks\Fireworks;
use Symfony\AI\Platform\Bridge\Fireworks\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalogTest extends TestCase
{
    public function testGetModelLoadsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    ['name' => 'accounts/fireworks/models/kimi-k2p6', 'kind' => 'HF_BASE_MODEL', 'supportsTools' => true, 'supportsImageInput' => false],
                ],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient, 'api-key');
        $model = $catalog->getModel('accounts/fireworks/models/kimi-k2p6');

        $this->assertInstanceOf(Fireworks::class, $model);
        $this->assertSame('accounts/fireworks/models/kimi-k2p6', $model->getName());
        $this->assertContains(Capability::INPUT_MESSAGES, $model->getCapabilities());
        $this->assertContains(Capability::OUTPUT_TEXT, $model->getCapabilities());
        $this->assertContains(Capability::OUTPUT_STREAMING, $model->getCapabilities());
        $this->assertContains(Capability::TOOL_CALLING, $model->getCapabilities());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testGetModelDetectsEmbeddingModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    ['name' => 'accounts/fireworks/models/qwen3-embedding-8b', 'kind' => 'EMBEDDING_MODEL'],
                ],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient, 'api-key');
        $model = $catalog->getModel('accounts/fireworks/models/qwen3-embedding-8b');

        $this->assertContains(Capability::INPUT_TEXT, $model->getCapabilities());
        $this->assertContains(Capability::EMBEDDINGS, $model->getCapabilities());
    }

    public function testGetModelDetectsVisionModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    ['name' => 'accounts/fireworks/models/kimi-k2p6-vision', 'kind' => 'HF_BASE_MODEL', 'supportsImageInput' => true],
                ],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient, 'api-key');
        $model = $catalog->getModel('accounts/fireworks/models/kimi-k2p6-vision');

        $this->assertContains(Capability::INPUT_IMAGE, $model->getCapabilities());
    }

    public function testOverlayWinsOverApiForImageModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    ['name' => 'accounts/fireworks/models/flux-1-schnell-fp8', 'kind' => 'HF_BASE_MODEL'],
                ],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient, 'api-key');
        $model = $catalog->getModel('accounts/fireworks/models/flux-1-schnell-fp8');

        // The overlay declares image generation; the gateway chat-only capabilities must not win.
        $this->assertContains(Capability::TEXT_TO_IMAGE, $model->getCapabilities());
        $this->assertNotContains(Capability::OUTPUT_TEXT, $model->getCapabilities());
    }

    public function testOverlayProvidesRerankModel()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['models' => []]));

        $catalog = new ModelCatalog($httpClient, 'api-key');
        $model = $catalog->getModel('accounts/fireworks/models/qwen3-reranker-8b');

        $this->assertContains(Capability::RERANKING, $model->getCapabilities());
    }

    public function testModelsAreOnlyLoadedOnce()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [['name' => 'accounts/fireworks/models/kimi-k2p6', 'kind' => 'HF_BASE_MODEL']],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient, 'api-key');
        $catalog->getModels();
        $catalog->getModels();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testGetModelsPaginates()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [['name' => 'accounts/fireworks/models/kimi-k2p6', 'kind' => 'HF_BASE_MODEL']],
                'nextPageToken' => 'page2',
            ]),
            new JsonMockResponse([
                'models' => [['name' => 'accounts/fireworks/models/gpt-oss-120b', 'kind' => 'HF_BASE_MODEL', 'supportsTools' => true]],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient, 'api-key');
        $models = $catalog->getModels();

        $this->assertArrayHasKey('accounts/fireworks/models/kimi-k2p6', $models);
        $this->assertArrayHasKey('accounts/fireworks/models/gpt-oss-120b', $models);
        $this->assertSame(2, $httpClient->getRequestsCount());
    }
}

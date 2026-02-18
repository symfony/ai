<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AmazeeAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\AmazeeAi\ModelApiCatalog;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelApiCatalogTest extends TestCase
{
    public function testLazyLoadModels()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $models = $catalog->getModels();

        $this->assertArrayHasKey('claude-3-5-sonnet', $models);
        $this->assertArrayHasKey('titan-embed-text-v2:0', $models);
    }

    public function testCompletionsModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $model = $catalog->getModel('claude-3-5-sonnet');

        $this->assertInstanceOf(CompletionsModel::class, $model);
    }

    public function testEmbeddingsModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $model = $catalog->getModel('titan-embed-text-v2:0');

        $this->assertInstanceOf(EmbeddingsModel::class, $model);
    }

    public function testCompletionsCapabilities()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $models = $catalog->getModels();
        $capabilities = $models['claude-3-5-sonnet']['capabilities'];

        $this->assertContains(Capability::INPUT_MESSAGES, $capabilities);
        $this->assertContains(Capability::OUTPUT_TEXT, $capabilities);
        $this->assertContains(Capability::OUTPUT_STREAMING, $capabilities);
        $this->assertContains(Capability::INPUT_IMAGE, $capabilities);
        $this->assertContains(Capability::TOOL_CALLING, $capabilities);
        $this->assertContains(Capability::OUTPUT_STRUCTURED, $capabilities);
    }

    public function testEmbeddingCapabilities()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $models = $catalog->getModels();
        $capabilities = $models['titan-embed-text-v2:0']['capabilities'];

        $this->assertContains(Capability::EMBEDDINGS, $capabilities);
        $this->assertContains(Capability::INPUT_TEXT, $capabilities);
        $this->assertContains(Capability::INPUT_MULTIPLE, $capabilities);
    }

    public function testModelNotFound()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $this->expectException(ModelNotFoundException::class);
        $catalog->getModel('non-existent-model');
    }

    public function testModelsAreLoadedOnlyOnce()
    {
        $callCount = 0;
        $httpClient = new MockHttpClient(function () use (&$callCount) {
            ++$callCount;

            return new JsonMockResponse($this->getModelInfoResponse());
        });

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $catalog->getModels();
        $catalog->getModels();
        $catalog->getModel('claude-3-5-sonnet');

        $this->assertSame(1, $callCount);
    }

    public function testWithoutApiKey()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com');

        $models = $catalog->getModels();

        $this->assertNotEmpty($models);
    }

    /**
     * @return array<string, mixed>
     */
    private function getModelInfoResponse(): array
    {
        return [
            'data' => [
                [
                    'model_name' => 'claude-3-5-sonnet',
                    'model_info' => [
                        'mode' => 'chat',
                        'supports_image_input' => true,
                        'supports_audio_input' => false,
                        'supports_tool_calling' => true,
                        'supports_response_schema' => true,
                    ],
                ],
                [
                    'model_name' => 'claude-3-5-haiku',
                    'model_info' => [
                        'mode' => 'chat',
                        'supports_image_input' => false,
                        'supports_tool_calling' => true,
                        'supports_response_schema' => false,
                    ],
                ],
                [
                    'model_name' => 'titan-embed-text-v2:0',
                    'model_info' => [
                        'mode' => 'embedding',
                        'supports_multiple_inputs' => true,
                    ],
                ],
            ],
        ];
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\ApiClient;
use Symfony\AI\Platform\Bridge\HuggingFace\Completions;
use Symfony\AI\Platform\Bridge\HuggingFace\Embeddings;
use Symfony\AI\Platform\Bridge\HuggingFace\ModelApiCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelApiCatalogTest extends TestCase
{
    public function testResolvesModelThroughTheHubApi()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests), 'hf_test'));

        $model = $catalog->getModel('meta-llama/Llama-3.1-8B-Instruct');

        $this->assertInstanceOf(Completions::class, $model);
        $this->assertSame('meta-llama/Llama-3.1-8B-Instruct', $model->getName());
        $this->assertTrue($model->supports(Capability::TOOL_CALLING));
        $this->assertCount(1, $requests);
        $this->assertStringStartsWith('https://huggingface.co/api/models?', $requests[0]['url']);
        $this->assertStringContainsString('search=meta-llama/Llama-3.1-8B-Instruct', $requests[0]['url']);
        $this->assertContains('Authorization: Bearer hf_test', $requests[0]['headers']);
    }

    public function testResolvesCapabilitiesOfTheConfiguredProvider()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests)), 'novita');

        $this->assertFalse($catalog->getModel('meta-llama/Llama-3.1-8B-Instruct')->supports(Capability::TOOL_CALLING));
    }

    public function testRequestsEachModelOnce()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests)));

        $catalog->getModel('meta-llama/Llama-3.1-8B-Instruct');
        $model = $catalog->getModel('meta-llama/Llama-3.1-8B-Instruct?temperature=0.5');

        $this->assertSame(['temperature' => 0.5], $model->getOptions());
        $this->assertCount(1, $requests);
    }

    public function testKeepsTheProviderSuffixOfTheModelName()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests)));

        $model = $catalog->getModel('meta-llama/Llama-3.1-8B-Instruct:deepinfra');

        $this->assertInstanceOf(Completions::class, $model);
        $this->assertSame('meta-llama/Llama-3.1-8B-Instruct:deepinfra', $model->getName());
        $this->assertStringContainsString('search=meta-llama/Llama-3.1-8B-Instruct&', $requests[0]['url']);
    }

    public function testThrowsForModelNotServedByTheConfiguredProvider()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests)), 'cerebras');

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model "meta-llama/Llama-3.1-8B-Instruct" not found: it is not served for a supported task on provider "cerebras".');

        $catalog->getModel('meta-llama/Llama-3.1-8B-Instruct');
    }

    public function testThrowsForUnknownModel()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests)));

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model "meta-llama/Llama-3.1-8B" not found');

        $catalog->getModel('meta-llama/Llama-3.1-8B');
    }

    public function testRequestsEachUnknownModelOnce()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests)));

        for ($i = 0; $i < 2; ++$i) {
            try {
                $catalog->getModel('gpt-4o');
                $this->fail('Expected ModelNotFoundException.');
            } catch (ModelNotFoundException $exception) {
                $this->assertSame('Model "gpt-4o" not found: it is not served for a supported task.', $exception->getMessage());
            }
        }

        $this->assertCount(1, $requests);
    }

    public function testListsModelsOfTheConfiguredProvider()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests)), 'deepinfra');

        $models = $catalog->getModels();

        $this->assertSame(['meta-llama/Llama-3.1-8B-Instruct', 'BAAI/bge-small-en-v1.5'], array_keys($models));
        $this->assertSame(Completions::class, $models['meta-llama/Llama-3.1-8B-Instruct']['class']);
        $this->assertSame(Embeddings::class, $models['BAAI/bge-small-en-v1.5']['class']);
        $this->assertCount(2, $requests);
        $this->assertStringContainsString('inference_provider=deepinfra', $requests[0]['url']);
        $this->assertStringContainsString('inference_provider=hf-inference', $requests[1]['url']);
        $this->assertStringContainsString('sort=downloads', $requests[0]['url']);

        $catalog->getModels();
        $this->assertCount(2, $requests);
    }

    public function testListsTheHuggingFaceProviderOnce()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests)), 'hf-inference');

        $this->assertSame(['BAAI/bge-small-en-v1.5'], array_keys($catalog->getModels()));
        $this->assertCount(1, $requests);
        $this->assertStringContainsString('inference_provider=hf-inference', $requests[0]['url']);
    }

    public function testListsModelsOfAllProviders()
    {
        $requests = [];
        $catalog = new ModelApiCatalog(new ApiClient(self::createHttpClient($requests)));

        $this->assertSame(['meta-llama/Llama-3.1-8B-Instruct', 'BAAI/bge-small-en-v1.5'], array_keys($catalog->getModels()));
        $this->assertCount(1, $requests);
        $this->assertStringContainsString('inference_provider=all', $requests[0]['url']);
    }

    /**
     * @param list<array{url: string, headers: list<string>}> $requests
     */
    private static function createHttpClient(array &$requests): MockHttpClient
    {
        $models = [
            [
                'id' => 'meta-llama/Llama-3.1-8B-Instruct',
                'pipeline_tag' => 'text-generation',
                'inferenceProviderMapping' => [
                    ['provider' => 'novita', 'status' => 'live', 'task' => 'conversational', 'type' => 'single-model', 'features' => ['structuredOutput' => false, 'toolCalling' => false]],
                    ['provider' => 'deepinfra', 'status' => 'live', 'task' => 'conversational', 'type' => 'single-model', 'features' => ['structuredOutput' => false, 'toolCalling' => true]],
                ],
            ],
            [
                'id' => 'meta-llama/Llama-3.1-8B-Instruct-FP8',
                'pipeline_tag' => 'text-generation',
                'inferenceProviderMapping' => [],
            ],
            [
                'id' => 'BAAI/bge-small-en-v1.5',
                'pipeline_tag' => 'feature-extraction',
                'inferenceProviderMapping' => [
                    ['provider' => 'hf-inference', 'status' => 'live', 'task' => 'feature-extraction', 'type' => 'single-model'],
                ],
            ],
            [
                'id' => 'some-user/flux-lora',
                'pipeline_tag' => 'text-to-image',
                'inferenceProviderMapping' => [
                    ['provider' => 'fal-ai', 'status' => 'live', 'task' => 'text-to-image', 'type' => 'tag-filter'],
                ],
            ],
        ];

        return new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $models): JsonMockResponse {
            $requests[] = ['url' => $url, 'headers' => $options['headers']];
            parse_str(parse_url($url, \PHP_URL_QUERY) ?: '', $query);
            $search = \is_string($query['search'] ?? null) ? $query['search'] : '';
            $provider = \is_string($query['inference_provider'] ?? null) ? $query['inference_provider'] : 'all';

            return new JsonMockResponse(array_values(array_filter($models, static function (array $model) use ($search, $provider): bool {
                $providers = array_column($model['inferenceProviderMapping'], 'provider');

                return str_contains($model['id'], $search) && ('all' === $provider || \in_array($provider, $providers, true));
            })));
        });
    }
}

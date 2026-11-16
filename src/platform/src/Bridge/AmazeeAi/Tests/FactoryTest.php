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
use Symfony\AI\Platform\Bridge\AmazeeAi\Factory;
use Symfony\AI\Platform\Bridge\AmazeeAi\ModelApiCatalog;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\Generic\FallbackModelCatalog;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FactoryTest extends TestCase
{
    public function testCreateWithDefaults()
    {
        $platform = Factory::createPlatform(
            'https://litellm.example.com',
            'test-api-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateWithCustomHttpClient()
    {
        $httpClient = new MockHttpClient();

        $platform = Factory::createPlatform(
            'https://litellm.example.com',
            'test-api-key',
            $httpClient,
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());

        $platform = Factory::createPlatform(
            'https://litellm.example.com',
            'test-api-key',
            $httpClient,
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testDefaultCatalogCreatesCompletionsModel()
    {
        $catalog = new FallbackModelCatalog();
        $model = $catalog->getModel('gpt-4o');

        $this->assertInstanceOf(CompletionsModel::class, $model);
    }

    public function testDefaultCatalogCreatesEmbeddingsModel()
    {
        $catalog = new FallbackModelCatalog();
        $model = $catalog->getModel('text-embedding-3-small');

        $this->assertInstanceOf(EmbeddingsModel::class, $model);
    }

    public function testCreateModelCatalogFetchesModelsWithApiKey()
    {
        $requestedUrl = '';
        $headers = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedUrl, &$headers): ResponseInterface {
            $requestedUrl = $url;
            $headers = $options['normalized_headers'] ?? [];

            return new MockResponse('{"data": [{"model_name": "gpt-4o", "model_info": {"mode": "chat"}}]}');
        });

        $catalog = Factory::createModelCatalog('https://llm.example.com', 'secret', $httpClient);

        $this->assertInstanceOf(ModelApiCatalog::class, $catalog);
        $this->assertInstanceOf(CompletionsModel::class, $catalog->getModel('gpt-4o'));
        $this->assertSame('https://llm.example.com/model/info', $requestedUrl);
        $this->assertSame(['Authorization: Bearer secret'], $headers['authorization'] ?? []);
    }
}

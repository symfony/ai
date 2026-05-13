<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Inworld\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Inworld\Factory;
use Symfony\AI\Platform\Bridge\Inworld\ModelCatalog;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class FactoryTest extends TestCase
{
    public function testProviderCanBeCreatedWithApiKeyAndHttpClient()
    {
        $provider = Factory::createProvider(apiKey: 'api-key', httpClient: HttpClient::create());

        $this->assertInstanceOf(ModelCatalog::class, $provider->getModelCatalog());
    }

    public function testProviderCanBeCreatedWithPreconfiguredScopingHttpClient()
    {
        $provider = Factory::createProvider(httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'https://api.inworld.ai/', [
            'headers' => [
                'Authorization' => 'Basic api-key',
            ],
        ]));

        $this->assertInstanceOf(ModelCatalog::class, $provider->getModelCatalog());
    }

    public function testProviderAttachesBasicAuthorizationHeader()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.inworld.ai/llm/v1alpha/models', $url);
            $this->assertIsIterable($options['headers']);
            $this->assertContains('Authorization: Basic api-key', $options['headers']);

            return new JsonMockResponse(['models' => []]);
        });

        $provider = Factory::createProvider(apiKey: 'api-key', httpClient: $httpClient);
        $provider->getModelCatalog()->getModels();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}

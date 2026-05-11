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
use Symfony\AI\Platform\Bridge\Venice\Factory;
use Symfony\AI\Platform\Bridge\Venice\ModelCatalog;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class FactoryTest extends TestCase
{
    public function testProviderCanBeCreatedWithHttpClientAndApiKey()
    {
        $provider = Factory::createProvider(
            endpoint: 'https://api.venice.ai/api/v1/',
            apiKey: 'venice-api-key',
            httpClient: HttpClient::create(),
        );

        $this->assertInstanceOf(ModelCatalog::class, $provider->getModelCatalog());
        $this->assertSame('venice', $provider->getName());
    }

    public function testProviderCanBeCreatedWithoutApiKey()
    {
        $provider = Factory::createProvider(
            endpoint: 'https://api.venice.ai/api/v1/',
            httpClient: HttpClient::create(),
        );

        $this->assertInstanceOf(ModelCatalog::class, $provider->getModelCatalog());
    }

    public function testProviderCanBeCreatedWithScopingHttpClient()
    {
        $provider = Factory::createProvider(
            endpoint: 'https://api.venice.ai/api/v1/',
            httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'https://api.venice.ai/api/v1/', [
                'auth_bearer' => 'venice-api-key',
            ]),
        );

        $this->assertInstanceOf(ModelCatalog::class, $provider->getModelCatalog());
    }

    public function testProviderCanBeCreatedWithCustomName()
    {
        $provider = Factory::createProvider(
            endpoint: 'https://api.venice.ai/api/v1/',
            apiKey: 'venice-api-key',
            httpClient: HttpClient::create(),
            name: 'venice-custom',
        );

        $this->assertSame('venice-custom', $provider->getName());
    }

    public function testPlatformCanBeCreatedWithDefaultEndpoint()
    {
        $platform = Factory::createPlatform(apiKey: 'venice-api-key', httpClient: HttpClient::create());

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testPlatformCanBeCreatedWithCustomEndpoint()
    {
        $platform = Factory::createPlatform(
            apiKey: 'venice-api-key',
            endpoint: 'https://custom-venice.example.com/v1/',
            httpClient: HttpClient::create(),
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }
}

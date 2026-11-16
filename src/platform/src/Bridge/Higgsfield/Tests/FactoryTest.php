<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Higgsfield\Factory;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FactoryTest extends TestCase
{
    public function testItCreatesPlatformWithDefaultSettings()
    {
        $platform = Factory::createPlatform('key-id', 'key-secret', httpClient: new MockHttpClient());

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesProvider()
    {
        $provider = Factory::createProvider('key-id', 'key-secret', httpClient: new MockHttpClient());

        $this->assertInstanceOf(Provider::class, $provider);
        $this->assertSame('higgsfield', $provider->getName());
    }

    public function testItScopesTheHttpClientToTheApiWithCredentials()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://platform.higgsfield.ai/models', $url);
            $this->assertSame('Authorization: Key key-id:key-secret', $options['normalized_headers']['authorization'][0]);

            return new JsonMockResponse(['items' => []]);
        });

        Factory::createProvider('key-id', 'key-secret', httpClient: $httpClient)
            ->getModelCatalog()
            ->getModels();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesACustomBaseUrl()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('https://higgsfield.example.com/models', $url);

            return new JsonMockResponse(['items' => []]);
        });

        Factory::createProvider('key-id', 'key-secret', 'https://higgsfield.example.com', $httpClient)
            ->getModelCatalog()
            ->getModels();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCreateModelCatalogUsesScopedHttpClient()
    {
        $requestedUrl = '';
        $headers = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedUrl, &$headers): ResponseInterface {
            $requestedUrl = $url;
            $headers = $options['normalized_headers'] ?? [];

            return new MockResponse('{"data": []}');
        });

        Factory::createModelCatalog('key', 'secret', 'https://platform.higgsfield.ai', $httpClient)->getModels();

        $this->assertSame('https://platform.higgsfield.ai/models', $requestedUrl);
        $this->assertSame(['Authorization: Key key:secret'], $headers['authorization'] ?? []);
    }
}

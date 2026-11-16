<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenRouter\Factory;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelApiCatalog;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesPlatformWithDefaultSettings()
    {
        $platform = Factory::createPlatform('test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomHttpClient()
    {
        $httpClient = new MockHttpClient();
        $platform = Factory::createPlatform('test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateModelCatalogFetchesModelsFromBaseUrl()
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrls): ResponseInterface {
            $requestedUrls[] = $url;

            return new MockResponse('{"data": []}');
        });

        $catalog = Factory::createModelCatalog($httpClient, 'https://gateway.example.com');

        $this->assertInstanceOf(ModelApiCatalog::class, $catalog);
        $catalog->getModels();
        $this->assertContains('https://gateway.example.com/v1/models', $requestedUrls);
    }
}

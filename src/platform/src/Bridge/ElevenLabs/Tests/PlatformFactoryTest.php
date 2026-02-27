<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsApiCatalog;
use Symfony\AI\Platform\Bridge\ElevenLabs\ModelCatalog;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class PlatformFactoryTest extends TestCase
{
    public function testStoreCanBeCreatedWithHttpClientAndRequiredInfos()
    {
        $platform = PlatformFactory::create(apiKey: 'foo', httpClient: HttpClient::create());

        $this->assertInstanceOf(ModelCatalog::class, $platform->getModelCatalog());
    }

    public function testStoreCanBeCreatedWithoutScopingHttpClientAndApiCatalog()
    {
        $platform = PlatformFactory::create(apiKey: 'foo', httpClient: HttpClient::create(), apiCatalog: true);

        $this->assertInstanceOf(ElevenLabsApiCatalog::class, $platform->getModelCatalog());
    }

    public function testStoreCanBeCreatedWithScopingHttpClient()
    {
        $platform = PlatformFactory::create(httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'https://api.elevenlabs.io/v1/', [
            'headers' => [
                'xi-api-key' => 'bar',
            ],
        ]));

        $this->assertInstanceOf(ModelCatalog::class, $platform->getModelCatalog());
    }

    public function testStoreCanBeCreatedWithScopingHttpClientAndApiCatalog()
    {
        $platform = PlatformFactory::create(httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'https://api.elevenlabs.io/v1/', [
            'headers' => [
                'xi-api-key' => 'bar',
            ],
        ]), apiCatalog: true);

        $this->assertInstanceOf(ElevenLabsApiCatalog::class, $platform->getModelCatalog());
    }
}

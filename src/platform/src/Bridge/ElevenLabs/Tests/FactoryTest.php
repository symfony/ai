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
use Symfony\AI\Platform\Bridge\ElevenLabs\Factory;
use Symfony\AI\Platform\Bridge\ElevenLabs\ModelCatalog;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class FactoryTest extends TestCase
{
    public function testProviderCanBeCreatedWithHttpClientAndRequiredInfos()
    {
        $provider = Factory::createProvider(apiKey: 'foo', httpClient: HttpClient::create());

        $this->assertInstanceOf(ModelCatalog::class, $provider->getModelCatalog());
    }

    public function testProviderCanBeCreatedWithScopingHttpClient()
    {
        $provider = Factory::createProvider(httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'https://api.elevenlabs.io/v1/', [
            'headers' => [
                'xi-api-key' => 'bar',
            ],
        ]));

        $this->assertInstanceOf(ModelCatalog::class, $provider->getModelCatalog());
    }
}

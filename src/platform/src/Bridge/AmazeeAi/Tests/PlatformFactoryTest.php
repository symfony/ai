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
use Symfony\AI\Platform\Bridge\AmazeeAi\PlatformFactory;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\Generic\FallbackModelCatalog;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;

final class PlatformFactoryTest extends TestCase
{
    public function testCreateWithDefaults()
    {
        $platform = PlatformFactory::create(
            'https://litellm.example.com',
            'test-api-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateWithCustomHttpClient()
    {
        $httpClient = new MockHttpClient();

        $platform = PlatformFactory::create(
            'https://litellm.example.com',
            'test-api-key',
            $httpClient,
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());

        $platform = PlatformFactory::create(
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
}

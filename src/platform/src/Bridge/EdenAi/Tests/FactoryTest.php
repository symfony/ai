<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\Factory;
use Symfony\AI\Platform\Bridge\EdenAi\ModelApiCatalog;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
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

    public function testItCreatesProviderSupportingExpertModels()
    {
        $provider = Factory::createProvider('test-api-key', new MockHttpClient());

        $this->assertTrue($provider->supports('ocr/ocr/google'));
        $this->assertTrue($provider->supports('ocr/financial_parser/affinda'));
        $this->assertTrue($provider->supports('audio/tts/amazon/neural'));
        $this->assertTrue($provider->supports('audio/speech_to_text_async/openai'));
        $this->assertTrue($provider->supports('image/object_detection/google'));
        $this->assertTrue($provider->supports('image/generation/openai'));
        $this->assertTrue($provider->supports('openai/gpt-4o'));
    }

    public function testCreateModelCatalogFetchesModelsFromBaseUrl()
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrls): ResponseInterface {
            $requestedUrls[] = $url;

            return new MockResponse('{"data": [], "features": []}');
        });

        $catalog = Factory::createModelCatalog($httpClient, 'https://gateway.example.com');

        $this->assertInstanceOf(ModelApiCatalog::class, $catalog);
        $catalog->getModels();
        $this->assertContains('https://gateway.example.com/v3/models', $requestedUrls);
    }
}

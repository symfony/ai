<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\Factory;
use Symfony\AI\Platform\Bridge\Deepgram\ModelCatalog;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FactoryTest extends TestCase
{
    public function testCreatePlatformReturnsPlatformInterface()
    {
        $platform = Factory::createPlatform(apiKey: 'test-key');

        $this->assertInstanceOf(PlatformInterface::class, $platform);
        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateProviderUsesDeepgramModelCatalog()
    {
        $provider = Factory::createProvider(apiKey: 'test-key');

        $this->assertInstanceOf(ModelCatalog::class, $provider->getModelCatalog());
    }

    public function testProviderNameDefaultsToDeepgram()
    {
        $provider = Factory::createProvider(apiKey: 'test-key');

        $this->assertSame('deepgram', $provider->getName());
    }

    public function testProviderNameIsCustomizable()
    {
        $provider = Factory::createProvider(apiKey: 'test-key', name: 'deepgram-eu');

        $this->assertSame('deepgram-eu', $provider->getName());
    }

    public function testHttpClientIsScopedWithAuthorizationHeader()
    {
        $requestedUrl = '';
        $authorizationHeader = '';
        $upstream = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestedUrl, &$authorizationHeader): ResponseInterface {
            $requestedUrl = $url;
            $authorizationHeader = $this->extractAuthorizationHeader($options);

            return new JsonMockResponse(['tts' => [], 'stt' => []]);
        });

        $provider = Factory::createProvider(apiKey: 'secret-token', httpClient: $upstream);
        $provider->getModelCatalog()->getModels();

        $this->assertSame('https://api.deepgram.com/v1/models', $requestedUrl);
        $this->assertSame('Authorization: Token secret-token', $authorizationHeader);
    }

    public function testEndpointWithoutTrailingSlashIsNormalized()
    {
        $requestedUrl = '';
        $upstream = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): ResponseInterface {
            $requestedUrl = $url;

            return new JsonMockResponse(['tts' => [], 'stt' => []]);
        });

        $provider = Factory::createProvider(endpoint: 'https://api.deepgram.com/v1', apiKey: 'secret-token', httpClient: $upstream);
        $provider->getModelCatalog()->getModels();

        $this->assertSame('https://api.deepgram.com/v1/models', $requestedUrl);
    }

    public function testHttpClientIsNotScopedWithoutApiKey()
    {
        $authorizationHeader = '';
        $upstream = new MockHttpClient(function (string $method, string $url, array $options) use (&$authorizationHeader): ResponseInterface {
            $authorizationHeader = $this->extractAuthorizationHeader($options);

            return new JsonMockResponse(['tts' => [], 'stt' => []]);
        }, 'https://scoped.example.com/v1/');

        $provider = Factory::createProvider(httpClient: $upstream);
        $provider->getModelCatalog()->getModels();

        $this->assertSame('', $authorizationHeader);
    }

    public function testCreatedPlatformInvokesTextToSpeechOverHttp()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url): ResponseInterface {
            if (str_contains($url, '/models')) {
                return new JsonMockResponse([
                    'tts' => [
                        ['name' => 'aura-2-thalia-en', 'canonical_name' => 'aura-2-thalia-en'],
                    ],
                    'stt' => [],
                ]);
            }

            return new MockResponse('audio-bytes', ['response_headers' => ['content-type' => 'audio/mpeg']]);
        });

        $platform = Factory::createPlatform(apiKey: 'test-key', httpClient: $httpClient);

        $result = $platform->invoke('aura-2-thalia-en', new Text('Hello world'));

        $this->assertSame('audio-bytes', $result->asBinary());
    }

    public function testCreateModelCatalogUsesScopedHttpClient()
    {
        $requestedUrl = '';
        $headers = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedUrl, &$headers): ResponseInterface {
            $requestedUrl = $url;
            $headers = $options['normalized_headers'] ?? [];

            return new MockResponse('{"stt": [], "tts": []}');
        });

        Factory::createModelCatalog('https://api.deepgram.com/v1', 'secret', $httpClient)->getModels();

        $this->assertSame('https://api.deepgram.com/v1/models', $requestedUrl);
        $this->assertSame(['Authorization: Token secret'], $headers['authorization'] ?? []);
    }

    public function testCreatePlatformUsesGivenModelCatalog()
    {
        $model = new Model('custom-model');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $platform = Factory::createPlatform(apiKey: 'secret', httpClient: new MockHttpClient(), modelCatalog: $catalog);

        $this->assertSame($model, $platform->getModelCatalog()->getModel('custom-model'));
    }

    /**
     * @param array<mixed> $options
     */
    private function extractAuthorizationHeader(array $options): string
    {
        $headers = $options['headers'] ?? [];
        if (!\is_array($headers)) {
            return '';
        }

        foreach ($headers as $header) {
            if (\is_string($header) && str_starts_with(strtolower($header), 'authorization:')) {
                return $header;
            }
        }

        return '';
    }
}

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
use Symfony\AI\Platform\Bridge\Deepgram\Contract\DeepgramContract;
use Symfony\AI\Platform\Bridge\Deepgram\DeepgramClient;
use Symfony\AI\Platform\Bridge\Deepgram\PlatformFactory;
use Symfony\AI\Platform\Bridge\Deepgram\WebsocketClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PlatformFactoryTest extends TestCase
{
    public function testCreatePlatformReturnsPlatformInterface()
    {
        $platform = PlatformFactory::createPlatform('test-key');

        $this->assertInstanceOf(PlatformInterface::class, $platform);
        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateProviderReturnsRestClient()
    {
        $provider = PlatformFactory::createProvider('test-key');

        $this->assertProviderHasModelClientOfType($provider, DeepgramClient::class);
    }

    public function testCreateProviderReturnsWebsocketClient()
    {
        $provider = PlatformFactory::createProvider('test-key', useWebsockets: true);

        $this->assertProviderHasModelClientOfType($provider, WebsocketClient::class);
    }

    public function testRejectsEmptyApiKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Deepgram API key cannot be empty.');

        PlatformFactory::createProvider('');
    }

    public function testHttpClientIsScopedWithAuthorizationHeader()
    {
        $authorizationHeader = '';
        $upstream = new MockHttpClient(static function (string $method, string $url, array $options) use (&$authorizationHeader) {
            $headers = $options['headers'] ?? [];
            if (\is_array($headers)) {
                foreach ($headers as $header) {
                    if (\is_string($header) && str_starts_with(strtolower($header), 'authorization:')) {
                        $authorizationHeader = $header;
                        break;
                    }
                }
            }

            return new MockResponse('{}');
        });

        $provider = PlatformFactory::createProvider('secret-token', httpClient: $upstream);

        $modelClient = $this->extractFirstModelClient($provider);
        $this->assertInstanceOf(DeepgramClient::class, $modelClient);

        $reflection = new \ReflectionProperty(DeepgramClient::class, 'httpClient');
        $http = $reflection->getValue($modelClient);
        $this->assertInstanceOf(\Symfony\Contracts\HttpClient\HttpClientInterface::class, $http);
        $http->request('GET', 'models');

        $this->assertSame('Authorization: Token secret-token', $authorizationHeader);
    }

    public function testCustomContractIsUsed()
    {
        $contract = DeepgramContract::create();
        $provider = PlatformFactory::createProvider('test-key', contract: $contract);

        $reflection = new \ReflectionProperty(Provider::class, 'contract');
        $this->assertSame($contract, $reflection->getValue($provider));
    }

    public function testProviderNameIsCustomizable()
    {
        $provider = PlatformFactory::createProvider('test-key', name: 'deepgram-eu');

        $this->assertSame('deepgram-eu', $provider->getName());
    }

    /**
     * @param class-string $expectedClass
     */
    private function assertProviderHasModelClientOfType(ProviderInterface $provider, string $expectedClass): void
    {
        $modelClient = $this->extractFirstModelClient($provider);
        $this->assertInstanceOf($expectedClass, $modelClient);
    }

    private function extractFirstModelClient(ProviderInterface $provider): object
    {
        $reflection = new \ReflectionProperty(Provider::class, 'modelClients');
        $clients = $reflection->getValue($provider);

        $this->assertIsIterable($clients);
        $clientList = [];
        foreach ($clients as $client) {
            $this->assertIsObject($client);
            $clientList[] = $client;
        }

        $this->assertNotEmpty($clientList);

        return $clientList[0];
    }
}

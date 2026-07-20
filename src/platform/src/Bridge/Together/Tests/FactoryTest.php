<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Together\Factory;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesPlatformWithDefaultSettings()
    {
        $platform = Factory::createPlatform(apiKey: 'together-test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomEndpoint()
    {
        $platform = Factory::createPlatform('https://example.test', 'together-test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomHttpClient()
    {
        $platform = Factory::createPlatform(apiKey: 'together-test-api-key', httpClient: new MockHttpClient());

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $platform = Factory::createPlatform(apiKey: 'together-test-api-key', httpClient: new EventSourceHttpClient(new MockHttpClient()));

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesProvider()
    {
        $provider = Factory::createProvider(apiKey: 'together-test-api-key');

        $this->assertInstanceOf(Provider::class, $provider);
        $this->assertSame('together', $provider->getName());
    }
}

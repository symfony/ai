<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bifrost\Factory;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesPlatformWithEndpointOnly()
    {
        $platform = Factory::createPlatform(null, 'http://localhost:8080');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEndpointAndApiKey()
    {
        $platform = Factory::createPlatform('sk-bf-test', 'http://localhost:8080');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomHttpClientOnly()
    {
        $platform = Factory::createPlatform(null, null, new MockHttpClient());

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('sk-bf-test', 'http://localhost:8080', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItFailsWhenNeitherEndpointNorHttpClientIsProvided()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either an "endpoint" or a pre-configured HTTP client (with a base URI) must be provided to the Bifrost factory.');

        Factory::createPlatform();
    }
}

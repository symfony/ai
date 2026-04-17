<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Factory;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * @author Johannes Wachter <johannes@sulu.io>
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
}

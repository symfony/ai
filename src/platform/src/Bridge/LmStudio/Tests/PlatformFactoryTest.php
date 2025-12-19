<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LmStudio\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\LmStudio\PlatformFactory;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class PlatformFactoryTest extends TestCase
{
    public function testItCreatesPlatformWithDefaultSettings()
    {
        $platform = PlatformFactory::create();

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomBaseUrl()
    {
        $platform = PlatformFactory::create('http://localhost:8080');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomHttpClient()
    {
        $httpClient = new MockHttpClient();
        $platform = PlatformFactory::create('http://localhost:1234', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = PlatformFactory::create('http://localhost:1234', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesPlatformWithDefaultSettings()
    {
        $platform = Factory::createPlatform('sk-test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomHttpClient()
    {
        $httpClient = new MockHttpClient();
        $platform = Factory::createPlatform('sk-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('sk-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }
}

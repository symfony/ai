<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Platform;

final class FactoryTest extends TestCase
{
    public function testCreateProviderReturnsProviderWithDefaultName()
    {
        $provider = Factory::createProvider('sk-test');

        $this->assertSame('openai', $provider->getName());
    }

    public function testCreateProviderWithCustomName()
    {
        $provider = Factory::createProvider('sk-test', name: 'openai-eu');

        $this->assertSame('openai-eu', $provider->getName());
    }

    public function testCreatePlatformReturnsPlatformInstance()
    {
        $platform = Factory::createPlatform('sk-test');

        $this->assertInstanceOf(Platform::class, $platform);
    }
}

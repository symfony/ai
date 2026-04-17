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
use Symfony\AI\Platform\Bridge\OpenAi\ProviderFactory;

final class ProviderFactoryTest extends TestCase
{
    public function testCreateReturnsProviderWithDefaultName()
    {
        $provider = ProviderFactory::create('sk-test');

        $this->assertSame('openai', $provider->getName());
    }

    public function testCreateWithCustomName()
    {
        $provider = ProviderFactory::create('sk-test', name: 'openai-eu');

        $this->assertSame('openai-eu', $provider->getName());
    }
}

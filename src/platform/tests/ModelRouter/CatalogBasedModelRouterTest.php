<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\ModelRouter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ProviderInterface;

final class CatalogBasedModelRouterTest extends TestCase
{
    public function testResolvesFirstSupportingProvider()
    {
        $provider1 = $this->createStub(ProviderInterface::class);
        $provider1->method('supports')->willReturn(false);
        $provider1->method('getName')->willReturn('anthropic');

        $provider2 = $this->createStub(ProviderInterface::class);
        $provider2->method('supports')->willReturn(true);
        $provider2->method('getName')->willReturn('openai');

        $router = new CatalogBasedModelRouter();
        $result = $router->resolve('gpt-4o', [$provider1, $provider2], 'Hello');

        $this->assertSame($provider2, $result);
    }

    public function testResolvesFirstProviderWhenMultipleSupport()
    {
        $provider1 = $this->createStub(ProviderInterface::class);
        $provider1->method('supports')->willReturn(true);
        $provider1->method('getName')->willReturn('openai');

        $provider2 = $this->createStub(ProviderInterface::class);
        $provider2->method('supports')->willReturn(true);
        $provider2->method('getName')->willReturn('openrouter');

        $router = new CatalogBasedModelRouter();
        $result = $router->resolve('gpt-4o', [$provider1, $provider2], 'Hello');

        $this->assertSame($provider1, $result);
    }

    public function testThrowsWhenNoProviderSupportsModel()
    {
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('supports')->willReturn(false);

        $router = new CatalogBasedModelRouter();

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessageMatches('/No provider found for model "unknown-model"/');

        $router->resolve('unknown-model', [$provider], 'Hello');
    }

    public function testThrowsWhenNoProvidersGiven()
    {
        $router = new CatalogBasedModelRouter();

        $this->expectException(ModelNotFoundException::class);

        $router->resolve('gpt-4o', [], 'Hello');
    }
}

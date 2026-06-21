<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Mock;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds a routing-aware fake provider/platform that returns scripted responses of any result type.
 *
 * Complements {@see \Symfony\AI\Platform\Test\InMemoryPlatform}: use this when a test must go
 * through real routing, coexist with real providers, return non-text results, or assert on the
 * exact payload/options the platform built (via {@see MockModelClient::getCalls()}).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Factory
{
    /**
     * @param \Closure|string|array<string, ResultInterface|string> $responses
     * @param non-empty-string                                      $name
     */
    public static function createProvider(
        \Closure|string|array $responses,
        ModelCatalogInterface $modelCatalog = new FallbackModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'fake',
    ): ProviderInterface {
        return new Provider(
            $name,
            [new MockModelClient($responses)],
            [new MockResultConverter()],
            $modelCatalog,
            $contract,
            $eventDispatcher,
        );
    }

    /**
     * @param \Closure|string|array<string, ResultInterface|string> $responses
     * @param non-empty-string                                      $name
     */
    public static function createPlatform(
        \Closure|string|array $responses,
        ModelCatalogInterface $modelCatalog = new FallbackModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'fake',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($responses, $modelCatalog, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}

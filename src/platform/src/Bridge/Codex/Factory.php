<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\Codex\Contract\CodexContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Factory
{
    /**
     * @param array<string, string|false> $environment
     * @param non-empty-string            $name
     */
    public static function createProvider(
        ?string $cliBinary = null,
        ?string $workingDirectory = null,
        ?float $timeout = 300,
        array $environment = [],
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        LoggerInterface $logger = new NullLogger(),
        string $name = 'codex',
    ): ProviderInterface {
        return new Provider(
            $name,
            [new ModelClient($cliBinary, $workingDirectory, $timeout, $environment, $logger)],
            [new ResultConverter()],
            $modelCatalog,
            $contract ?? CodexContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param array<string, string|false> $environment
     * @param non-empty-string            $name
     */
    public static function createPlatform(
        ?string $cliBinary = null,
        ?string $workingDirectory = null,
        ?float $timeout = 300,
        array $environment = [],
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        LoggerInterface $logger = new NullLogger(),
        string $name = 'codex',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($cliBinary, $workingDirectory, $timeout, $environment, $modelCatalog, $contract, $eventDispatcher, $logger, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}

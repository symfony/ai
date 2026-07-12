<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Factory for creating ACP provider and platform.
 */
class Factory
{
    /**
     * @param non-empty-string      $name
     * @param array<string, string> $environment
     */
    public static function createProvider(
        string $name = 'acp',
        ?string $command = null,
        ?string $workingDirectory = null,
        array $environment = [],
        ?callable $onStatus = null,
        ?LoggerInterface $logger = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): ProviderInterface {
        $command = $command ?? ($_ENV['ACP_BINARY'] ?? 'opencode acp');
        $args = $_ENV['ACP_ARGS'] ?? '';
        $fullCommand = trim("$command $args");

        $modelClient = new ModelClient(
            command: $fullCommand,
            workingDirectory: $workingDirectory,
            environment: $environment,
            onStatus: $onStatus,
            logger: $logger ?? new NullLogger(),
        );

        return new Provider(
            $name,
            [$modelClient],
            [new ResultConverter()],
            $modelCatalog,
            $contract ?? Contract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string      $name
     * @param array<string, string> $environment
     */
    public static function createPlatform(
        string $name = 'acp',
        ?string $command = null,
        ?string $workingDirectory = null,
        array $environment = [],
        ?callable $onStatus = null,
        ?LoggerInterface $logger = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($name, $command, $workingDirectory, $environment, $onStatus, $logger, $modelCatalog, $contract, $eventDispatcher)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}

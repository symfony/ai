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
use Symfony\AI\Platform\Bridge\Acp\Transport\ProcessTransport;
use Symfony\AI\Platform\Bridge\Acp\Transport\SocketTransport;
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
        string $transport = 'process',
        ?string $host = null,
        ?int $port = null,
    ): ProviderInterface {
        $logger ??= new NullLogger();
        $transportInstance = 'socket' === $transport
            ? new SocketTransport(\sprintf('tcp://%s:%d', $host, $port), $logger)
            : new ProcessTransport(
                trim(($command ?? ($_ENV['ACP_BINARY'] ?? '')).' '.($_ENV['ACP_ARGS'] ?? '')),
                $workingDirectory,
                $environment,
                $logger,
            );

        $modelClient = new ModelClient(
            command: $command ?? '',
            workingDirectory: $workingDirectory,
            environment: $environment,
            onStatus: $onStatus,
            logger: $logger,
            transport: $transportInstance,
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
        string $transport = 'process',
        ?string $host = null,
        ?int $port = null,
    ): Platform {
        return new Platform(
            [self::createProvider($name, $command, $workingDirectory, $environment, $onStatus, $logger, $modelCatalog, $contract, $eventDispatcher, $transport, $host, $port)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}

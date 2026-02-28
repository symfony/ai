<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\ClaudeCode\Contract\ClaudeCodeContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PlatformFactory
{
    /**
     * @param array<string, string|false> $environment
     */
    public static function create(
        ?string $cliBinary = null,
        ?string $workingDirectory = null,
        ?float $timeout = 300,
        array $environment = [],
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        LoggerInterface $logger = new NullLogger(),
    ): Platform {
        return new Platform(
            [new ModelClient($cliBinary, $workingDirectory, $timeout, $environment, $logger)],
            [new ResultConverter()],
            $modelCatalog,
            $contract ?? ClaudeCodeContract::create(),
            $eventDispatcher,
        );
    }
}

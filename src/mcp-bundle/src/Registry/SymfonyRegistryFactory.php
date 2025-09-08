<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Registry;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for creating configured SymfonyRegistry instances.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class SymfonyRegistryFactory
{
    /**
     * @param array<string, mixed> $serverCapabilitiesConfig
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly bool $discoveryEnabled,
        /** @var array<string> */
        private readonly array $discoveryDirectories,
        /** @var array<string> */
        private readonly array $discoveryExclude,
        private readonly array $serverCapabilitiesConfig = [],
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function create(): SymfonyRegistry
    {
        $registry = new SymfonyRegistry($this->logger, $this->serverCapabilitiesConfig);

        if ($this->discoveryEnabled) {
            $registry->discoverTools(
                $this->projectDir,
                $this->discoveryDirectories,
                $this->discoveryExclude
            );
        }

        return $registry;
    }
}

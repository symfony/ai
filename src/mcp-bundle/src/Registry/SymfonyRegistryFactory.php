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
    public function __construct(
        private readonly string $projectDir,
        private readonly bool $discoveryEnabled,
        private readonly array $discoveryDirectories,
        private readonly array $discoveryExclude,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function create(): SymfonyRegistry
    {
        $registry = new SymfonyRegistry($this->logger);

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

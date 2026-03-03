<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Service;

use Mcp\Capability\Registry;
use Mcp\Capability\RegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;

/**
 * Provides a shared Registry instance populated with discovered capabilities.
 *
 * The Registry is lazily initialized on first access to avoid unnecessary
 * capability discovery during container compilation.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RegistryProvider
{
    private ?RegistryInterface $registry = null;

    public function __construct(
        private FilteredDiscoveryLoader $loader,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Gets the Registry instance, creating and populating it on first access.
     */
    public function getRegistry(): RegistryInterface
    {
        if (null === $this->registry) {
            $this->registry = new Registry(null, $this->logger);
            $this->loader->load($this->registry);
        }

        return $this->registry;
    }
}

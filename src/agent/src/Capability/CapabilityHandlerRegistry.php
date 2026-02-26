<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Capability;

use Symfony\AI\Agent\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CapabilityHandlerRegistry implements CapabilityHandlerRegistryInterface
{
    /**
     * @param CapabilityHandlerInterface[] $handlers
     */
    public function __construct(
        private readonly iterable $handlers = [],
    ) {
    }

    public function get(InputCapabilityInterface|OutputCapabilityInterface $capability): CapabilityHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if (!$handler->support($capability)) {
                continue;
            }

            return $handler;
        }

        throw new InvalidArgumentException(\sprintf('No capability handler found for the "%s" capability.', $capability::class));
    }
}

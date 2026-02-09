<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Policy;

use Symfony\AI\Agent\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class PolicyHandlerRegistry implements PolicyHandlerRegistryInterface
{
    /**
     * @param PolicyHandlerInterface[] $handlers
     */
    public function __construct(
        private readonly iterable $handlers = [],
    ) {
    }

    public function get(InputPolicyInterface|OutputPolicyInterface $policy): PolicyHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if (!$handler->support($policy)) {
                continue;
            }

            return $handler;
        }

        throw new InvalidArgumentException(\sprintf('No policy handler found for the "%s" policy.', $policy::class));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\Exception\InterruptedException;
use Symfony\AI\Platform\Result\InterruptionSignalInterface;

/**
 * Helpers shared by agents that honour an `options['interruption_signal']`
 * to abort cooperatively at phase boundaries.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
trait InterruptibleTrait
{
    /**
     * @param array<string, mixed> $options
     */
    private function extractInterruptionSignal(array $options): ?InterruptionSignalInterface
    {
        $signal = $options['interruption_signal'] ?? null;

        if (null === $signal) {
            return null;
        }

        if (!$signal instanceof InterruptionSignalInterface) {
            throw new InvalidArgumentException(\sprintf('The "interruption_signal" option must be an instance of "%s", "%s" given.', InterruptionSignalInterface::class, get_debug_type($signal)));
        }

        return $signal;
    }

    private function checkInterruptionSignal(?InterruptionSignalInterface $signal): void
    {
        if (null !== $signal && $signal->isInterrupted()) {
            throw new InterruptedException();
        }
    }
}

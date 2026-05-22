<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class InterruptionSignal implements InterruptionSignalInterface
{
    private bool $interrupted = false;

    public function interrupt(): void
    {
        $this->interrupted = true;
    }

    public function isInterrupted(): bool
    {
        return $this->interrupted;
    }
}

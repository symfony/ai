<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

final class TransportException extends RecoverableRuntimeException implements DebugAwareExceptionInterface
{
    private string $debug = '';

    public function getDebug(): string
    {
        return $this->debug;
    }

    public function appendDebug(string $debug): void
    {
        if ('' === $debug) {
            return;
        }

        if ('' !== $this->debug) {
            $this->debug .= "\n";
        }

        $this->debug .= $debug;
    }
}

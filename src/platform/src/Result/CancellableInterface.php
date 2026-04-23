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
interface CancellableInterface
{
    /**
     * Cancels the underlying I/O if still in flight.
     *
     * Implementations must be idempotent: subsequent calls are no-ops.
     */
    public function cancel(): void;

    public function isCancelled(): bool;
}

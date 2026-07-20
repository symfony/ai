<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

/**
 * Opaque handle to an executor's in-flight work.
 *
 * Produced by {@see AsyncExecutorInterface::dispatch()} and passed back to
 * {@see AsyncExecutorInterface::settle()}; the executor owns the meaning of the handle.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class PendingExecution
{
    public function __construct(
        public readonly mixed $handle,
    ) {
    }
}

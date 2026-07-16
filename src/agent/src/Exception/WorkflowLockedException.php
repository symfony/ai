<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Exception;

/**
 * Exception thrown when a workflow run cannot start because the same run is locked by another process.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowLockedException extends RuntimeException
{
    public function __construct(string $id, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Workflow "%s" is already running in another process.', $id), 0, $previous);
    }
}

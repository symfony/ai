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
 * Exception thrown when a workflow exceeds the maximum number of execution steps.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowMaxStepsExceededException extends RuntimeException
{
    public function __construct(int $maxSteps, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('Workflow exceeded the maximum number of steps (%d); the workflow definition is likely cyclic.', $maxSteps),
            0,
            $previous,
        );
    }
}

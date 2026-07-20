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

use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * Exception thrown when a parallel workflow branch fails.
 *
 * Besides the failing place, it carries the result states of the branches that did complete before
 * the failure, so the engine can persist their progress and resume only the still-pending branches.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowBranchException extends RuntimeException
{
    /**
     * @param non-empty-string                                $place             The branch place that failed
     * @param array<non-empty-string, WorkflowStateInterface> $completedBranches Result states of the branches that completed before the failure, keyed by place
     */
    public function __construct(
        private readonly string $place,
        \Throwable $previous,
        private readonly array $completedBranches = [],
    ) {
        parent::__construct(\sprintf('Workflow branch at place "%s" failed: "%s".', $place, $previous->getMessage()), 0, $previous);
    }

    /**
     * @return non-empty-string
     */
    public function getPlace(): string
    {
        return $this->place;
    }

    /**
     * @return array<non-empty-string, WorkflowStateInterface>
     */
    public function getCompletedBranches(): array
    {
        return $this->completedBranches;
    }
}

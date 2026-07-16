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

use Symfony\AI\Agent\Exception\WorkflowMergeConflictException;

/**
 * Resolves data conflicts when {@see WorkflowState::mergeBranches()} joins parallel branches.
 *
 * A conflict happens when two or more branches write different values to the same state key.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
enum MergePolicy: string
{
    /**
     * Throw a {@see WorkflowMergeConflictException} on any conflict (the default; forces disjoint keys).
     */
    case FailOnConflict = 'fail_on_conflict';

    /**
     * Keep the value written by the last branch (in place order).
     */
    case LastBranchWins = 'last_branch_wins';

    /**
     * Keep the value written by the first branch (in place order).
     */
    case FirstBranchWins = 'first_branch_wins';

    /**
     * Keep the first non-null value; fall back to the last value when all are null.
     */
    case PreferNonNull = 'prefer_non_null';

    /**
     * @param non-empty-list<array{place: string, value: mixed}> $writes
     */
    public function resolveConflict(string $key, array $writes): mixed
    {
        return match ($this) {
            self::FailOnConflict => throw new WorkflowMergeConflictException(\sprintf('Parallel branches wrote conflicting values to the workflow state key "%s" (from places: "%s"). Use disjoint keys per branch or a different merge policy.', $key, implode('", "', array_column($writes, 'place')))),
            self::FirstBranchWins => $writes[array_key_first($writes)]['value'],
            self::LastBranchWins => $writes[array_key_last($writes)]['value'],
            self::PreferNonNull => $this->firstNonNull($writes),
        };
    }

    /**
     * @param non-empty-list<array{place: string, value: mixed}> $writes
     */
    private function firstNonNull(array $writes): mixed
    {
        foreach ($writes as $write) {
            if (null !== $write['value']) {
                return $write['value'];
            }
        }

        return $writes[array_key_last($writes)]['value'];
    }
}

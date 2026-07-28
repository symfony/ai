<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Skill\Model;

/**
 * Cross-referenced view of one skill: its intent, its recorded facts and the filesystem reality.
 *
 * Shared by "skills:list" (which renders the columns) and "skills:validate" (which renders the
 * issues and turns them into an exit code).
 *
 * @phpstan-type SkillIssue array{level: 'warning'|'error', message: string}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillStatus
{
    /**
     * @param 'managed'|'override'                             $mode
     * @param 'managed'|'override'|'disabled'|'none'           $state
     * @param 'ok'|'disabled'|'not installed'|'stale'|'broken' $status
     * @param list<SkillIssue>                                 $issues
     */
    public function __construct(
        public readonly string $installedName,
        public readonly string $originalName,
        public readonly string $package,
        public readonly bool $enabled,
        public readonly string $mode,
        public readonly string $state,
        public readonly string $source,
        public readonly string $status,
        public readonly array $issues,
    ) {
    }

    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ('error' === $issue['level']) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->issues as $issue) {
            if ('warning' === $issue['level']) {
                return true;
            }
        }

        return false;
    }
}

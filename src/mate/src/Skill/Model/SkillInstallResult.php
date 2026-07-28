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
 * Outcome of a single {@see \Symfony\AI\Mate\Skill\SkillInstaller::install()} run.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillInstallResult
{
    /**
     * @param list<string>                                   $installed newly installed skills (not installed before this run)
     * @param list<string>                                   $removed   installed names pruned from the generated folders
     * @param array<string, string>                          $skipped   installed name => reason for skills that could not be built
     * @param list<string>                                   $active    installed names present in the generated folders after the run
     * @param list<string>                                   $notices   human-readable notices about the run
     * @param array<string, 'managed'|'override'|'disabled'> $states    installed name => resulting state
     */
    public function __construct(
        public readonly array $installed,
        public readonly array $removed,
        public readonly array $skipped,
        public readonly array $active,
        public readonly array $notices,
        public readonly array $states = [],
    ) {
    }
}

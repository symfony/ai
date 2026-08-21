<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Skill;

/**
 * Creates the symlink mirroring .agents/skills/ into .claude/skills/.
 *
 * Isolated behind an interface so the copy fallback taken on filesystems without symlink support
 * (most commonly Windows without developer mode) is reachable in tests.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface LinkerInterface
{
    /**
     * @param string $target   path the link should point to, relative to the link's own directory
     * @param string $linkPath absolute path of the link to create
     */
    public function link(string $target, string $linkPath): bool;
}

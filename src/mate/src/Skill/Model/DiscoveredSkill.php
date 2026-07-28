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
 * A skill declared by an extension (or the root project), located on disk before installation.
 *
 * The {@see $source} path is always the declared source (e.g. "vendor/acme/pkg/skills/my-skill"),
 * regardless of whether the skill is later built from an override copy in mate/skills/.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DiscoveredSkill
{
    public function __construct(
        /**
         * Owning package name, or "_custom" for the root project.
         */
        public readonly string $package,
        /**
         * Frontmatter name; equals the source directory name.
         */
        public readonly string $originalName,
        /**
         * Installed name, always "mate-<original_name>".
         */
        public readonly string $installedName,
        /**
         * Source directory, relative to the project root.
         */
        public readonly string $source,
        /**
         * Absolute path to the declared source directory.
         */
        public readonly string $absolutePath,
        public readonly string $description,
    ) {
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill;

/**
 * Load Agent Skills from sources (filesystem, database, etc).
 *
 * @see https://agentskills.io/specification
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SkillLoaderInterface
{
    /**
     * Loads a specific skill fully (Level 2 — full SKILL.md content).
     */
    public function loadSkill(string $name): ?SkillInterface;

    /**
     * @return array<string, SkillInterface> Indexed by skill name
     */
    public function loadSkills(): array;

    /**
     * Discovers all skills and returns their metadata (Level 1 — lightweight).
     *
     * @return array<string, SkillMetadataInterface> Indexed by skill name
     */
    public function discoverMetadata(): array;
}

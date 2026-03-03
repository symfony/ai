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
 * Composes multiple skill loaders into a single transparent loader.
 *
 * Skills are loaded from each loader in order. The first match wins for loadSkill(),
 * while loadSkills() and discoverMetadata() aggregate results from all loaders.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainSkillLoader implements SkillLoaderInterface
{
    /**
     * @param SkillLoaderInterface[] $loaders
     */
    public function __construct(
        private readonly array $loaders,
    ) {
    }

    public function loadSkill(string $name): ?SkillInterface
    {
        foreach ($this->loaders as $loader) {
            $skill = $loader->loadSkill($name);

            if (null !== $skill) {
                return $skill;
            }
        }

        return null;
    }

    public function loadSkills(): array
    {
        $skills = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->loadSkills() as $name => $skill) {
                $skills[$name] ??= $skill;
            }
        }

        return $skills;
    }

    public function discoverMetadata(): array
    {
        $metadata = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->discoverMetadata() as $name => $skillMetadata) {
                $metadata[$name] ??= $skillMetadata;
            }
        }

        return $metadata;
    }
}

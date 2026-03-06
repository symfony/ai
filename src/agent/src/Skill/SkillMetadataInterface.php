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
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SkillMetadataInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function getLicense(): ?string;

    /**
     * @return string[]
     */
    public function getAllowedTools(): array;

    public function getCompatibility(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    public function getAuthor(): ?string;

    public function getVersion(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getFrontmatter(): array;
}

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

use Symfony\AI\Agent\Exception\RuntimeException;

/**
 * Represents a fully loaded Agent Skill.
 *
 * A skill is a directory containing a SKILL.md file with YAML frontmatter
 * and Markdown instructions, plus optional scripts/, references/, and assets/ directories.
 *
 * @see https://agentskills.io/specification
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Skill implements SkillInterface
{
    public function __construct(
        private readonly string $body,
        private readonly SkillMetadataInterface $metadata,
        private readonly ?\Closure $scriptsLoader = null,
        private readonly ?\Closure $referencesLoader = null,
        private readonly ?\Closure $assetsLoader = null,
    ) {
    }

    public function getName(): string
    {
        return $this->metadata->getName();
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getDescription(): string
    {
        return $this->metadata->getDescription();
    }

    public function getMetadata(): SkillMetadataInterface
    {
        return $this->metadata;
    }

    /**
     * Returns the absolute path to a script file.
     *
     * @return string The absolute path to the script
     *
     * @throws RuntimeException if the script does not exist
     */
    public function loadScript(string $script): mixed
    {
        return ($this->scriptsLoader)($script);
    }

    public function loadReference(string $reference): mixed
    {
        return ($this->referencesLoader)($reference);
    }

    public function loadAsset(string $asset): mixed
    {
        return ($this->assetsLoader)($asset);
    }
}

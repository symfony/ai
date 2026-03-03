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

use Symfony\AI\Agent\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SkillParserInterface
{
    /**
     * Parses a SKILL.md file from a skill directory.
     *
     * @param string $directory Absolute path to the skill directory
     *
     * @throws InvalidArgumentException If SKILL.md is missing or malformed
     */
    public function parse(string $directory): SkillInterface;

    /**
     * Parses only the frontmatter metadata (Level 1 — lightweight discovery).
     *
     * @param string $directory Absolute path to the skill directory
     */
    public function parseMetadataOnly(string $directory): SkillMetadataInterface;

    /**
     * Parses raw SKILL.md content with optional resource loaders.
     *
     * This enables source-agnostic parsing (e.g. from GitHub, database, etc.)
     * where the caller provides the content and closures for loading sub-resources.
     *
     * @param string        $content          Raw SKILL.md content (frontmatter + body)
     * @param string        $source           Source identifier for error messages
     * @param \Closure|null $scriptsLoader    fn(string $script): string
     * @param \Closure|null $referencesLoader fn(string $reference): ?string
     * @param \Closure|null $assetsLoader     fn(string $asset): ?string
     *
     * @throws InvalidArgumentException If content is malformed
     */
    public function parseFromContent(
        string $content,
        string $source,
        ?\Closure $scriptsLoader = null,
        ?\Closure $referencesLoader = null,
        ?\Closure $assetsLoader = null,
    ): SkillInterface;

    /**
     * Parses only the frontmatter metadata from raw SKILL.md content.
     *
     * @param string $content Raw SKILL.md content (frontmatter + body)
     * @param string $source  Source identifier for error messages
     *
     * @throws InvalidArgumentException If content is malformed
     */
    public function parseMetadataFromContent(string $content, string $source): SkillMetadataInterface;
}

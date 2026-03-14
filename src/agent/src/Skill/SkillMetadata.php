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
use Symfony\Component\String\UnicodeString;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SkillMetadata implements SkillMetadataInterface
{
    private readonly string $name;

    /**
     * @param string               $name          Skill name (kebab-case, required)
     * @param string               $description   What the skill does and when to use it (required)
     * @param string|null          $license       SPDX license identifier
     * @param string[]             $allowedTools  Pre-approved tools the skill may use
     * @param string|null          $compatibility Agent compatibility hints
     * @param array<string, mixed> $metadata      Arbitrary metadata (author, version, etc.)
     * @param array<string, mixed> $frontmatter   Raw frontmatter data
     */
    public function __construct(
        string $name,
        private readonly string $description,
        private readonly ?string $license = null,
        private readonly array $allowedTools = [],
        private readonly ?string $compatibility = null,
        private readonly array $metadata = [],
        private readonly array $frontmatter = [],
    ) {
        $unicodeName = new UnicodeString($name);

        if ($unicodeName->isEmpty() || !$unicodeName->match('/^[a-z0-9]+(-[a-z0-9]+)*$/')) {
            throw new InvalidArgumentException(\sprintf('Skill name "%s" must be non-empty kebab-case (e.g. "my-skill").', $name));
        }

        $this->name = $unicodeName->toString();

        $unicodeDescription = new UnicodeString($description);

        if ($unicodeDescription->trim()->isEmpty()) {
            throw new InvalidArgumentException('Skill description must not be empty.');
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function getAllowedTools(): array
    {
        return $this->allowedTools;
    }

    public function getCompatibility(): ?string
    {
        return $this->compatibility;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getAuthor(): ?string
    {
        return $this->metadata['author'] ?? null;
    }

    public function getVersion(): ?string
    {
        return $this->metadata['version'] ?? null;
    }

    public function getFrontmatter(): array
    {
        return $this->frontmatter;
    }
}

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
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\UnicodeString;

/**
 * Parses a SKILL.md file into a Skill object.
 *
 * Handles YAML frontmatter extraction and validation per the Agent Skills spec.
 *
 * @see https://agentskills.io/specification
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SkillParser implements SkillParserInterface
{
    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function parse(string $directory): SkillInterface
    {
        $skillFile = (new UnicodeString($directory))->trimEnd('/').'/SKILL.md';

        if (!$this->filesystem->exists($skillFile)) {
            throw new InvalidArgumentException(\sprintf('SKILL.md not found in directory "%s".', $directory));
        }

        $content = $this->filesystem->readFile($skillFile);

        [$frontmatter, $body] = $this->extractFrontmatter($content, $skillFile);

        $metadata = $this->buildMetadata($frontmatter, $skillFile);

        return new Skill(
            $body,
            $metadata,
            function (string $script) use ($directory): string {
                $scriptsPath = $directory.'/scripts';

                if (!$this->filesystem->exists($scriptsPath) || !is_dir($scriptsPath)) {
                    throw new RuntimeException(\sprintf('Scripts directory not found in skill "%s".', $directory));
                }

                $scriptPath = $scriptsPath.'/'.$script;

                if (!$this->filesystem->exists($scriptPath)) {
                    throw new RuntimeException(\sprintf('Script "%s" not found in skill scripts directory.', $script));
                }

                return $scriptPath;
            },
            function (string $reference) use ($directory): ?string {
                $path = $this->filesystem->exists($directory.'/references') && is_dir($directory.'/references') ? $directory.'/references' : null;

                if (null === $path) {
                    return null;
                }

                return $this->filesystem->readFile($path.'/'.$reference);
            },
            function (string $asset) use ($directory): ?string {
                $path = $this->filesystem->exists($directory.'/assets') && is_dir($directory.'/assets') ? $directory.'/assets' : null;

                if (null === $path) {
                    return null;
                }

                return $this->filesystem->readFile($path.'/'.$asset);
            },
        );
    }

    public function parseMetadataOnly(string $directory): SkillMetadataInterface
    {
        $skillFile = (new UnicodeString($directory))->trimEnd('/').'/SKILL.md';

        if (!$this->filesystem->exists($skillFile)) {
            throw new InvalidArgumentException(\sprintf('SKILL.md not found in directory "%s".', $directory));
        }

        $content = $this->filesystem->readFile($skillFile);

        [$frontmatter] = $this->extractFrontmatter($content, $skillFile);

        return $this->buildMetadata($frontmatter, $skillFile);
    }

    public function parseFromContent(
        string $content,
        string $source,
        ?\Closure $scriptsLoader = null,
        ?\Closure $referencesLoader = null,
        ?\Closure $assetsLoader = null,
    ): SkillInterface {
        [$frontmatter, $body] = $this->extractFrontmatter($content, $source);

        $metadata = $this->buildMetadata($frontmatter, $source);

        return new Skill($body, $metadata, $scriptsLoader, $referencesLoader, $assetsLoader);
    }

    public function parseMetadataFromContent(string $content, string $source): SkillMetadataInterface
    {
        [$frontmatter] = $this->extractFrontmatter($content, $source);

        return $this->buildMetadata($frontmatter, $source);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function extractFrontmatter(string $content, string $file): array
    {
        $unicodeContent = new UnicodeString($content);
        $trimmedContent = $unicodeContent->trimStart();

        if (!$trimmedContent->startsWith('---')) {
            throw new InvalidArgumentException(\sprintf('SKILL.md "%s" must start with YAML frontmatter (--- delimiter).', $file));
        }

        $matches = $trimmedContent->match('/^---\s*\n(.+?)\n---\s*\n?(.*)/s');

        if ([] === $matches) {
            throw new InvalidArgumentException(\sprintf('Unable to parse YAML frontmatter in "%s".', $file));
        }

        $yamlString = $matches[1];
        $body = (new UnicodeString($matches[2]))->trim()->toString();

        $frontmatter = $this->parseSimpleYaml($yamlString);

        return [$frontmatter, $body];
    }

    /**
     * @param array<string, mixed> $frontmatter
     */
    private function buildMetadata(array $frontmatter, string $file): SkillMetadata
    {
        if (!isset($frontmatter['name']) || !\is_string($frontmatter['name'])) {
            throw new InvalidArgumentException(\sprintf('Missing or invalid required field "name" in "%s".', $file));
        }

        if (!isset($frontmatter['description']) || !\is_string($frontmatter['description'])) {
            throw new InvalidArgumentException(\sprintf('Missing or invalid required field "description" in "%s".', $file));
        }

        $allowedTools = [];
        if (isset($frontmatter['allowed-tools']) && \is_string($frontmatter['allowed-tools'])) {
            $toolsString = new UnicodeString($frontmatter['allowed-tools']);

            $allowedTools = array_map(
                static fn (UnicodeString $part): string => $part->trim()->toString(),
                $toolsString->split(' '),
            );

            $allowedTools = array_values(array_filter($allowedTools, static fn (string $t): bool => '' !== $t));
        }

        return new SkillMetadata(
            $frontmatter['name'],
            $frontmatter['description'],
            isset($frontmatter['license']) && \is_string($frontmatter['license']) ? $frontmatter['license'] : null,
            $allowedTools,
            isset($frontmatter['compatibility']) && \is_string($frontmatter['compatibility']) ? $frontmatter['compatibility'] : null,
            isset($frontmatter['metadata']) && \is_array($frontmatter['metadata']) ? $frontmatter['metadata'] : [],
            $frontmatter,
        );
    }

    /**
     * Simple YAML parser for flat/nested structures (no external dependency on symfony/yaml needed).
     *
     * @return array<string, mixed>
     */
    private function parseSimpleYaml(string $yaml): array
    {
        $result = [];
        $currentKey = null;
        $lines = (new UnicodeString($yaml))->split("\n");

        foreach ($lines as $line) {
            $unicodeLine = new UnicodeString($line);
            $trimmedLine = $unicodeLine->trim();

            if ($trimmedLine->isEmpty() || $trimmedLine->startsWith('#')) {
                continue;
            }

            $nestedMatch = $unicodeLine->match('/^(\s{2,})([\w][\w-]*):\s*(.*)$/');
            if ([] !== $nestedMatch && null !== $currentKey) {
                $value = (new UnicodeString($nestedMatch[3]))->trim()->trim('"\'')->toString();
                if (!isset($result[$currentKey]) || !\is_array($result[$currentKey])) {
                    $result[$currentKey] = [];
                }

                $result[$currentKey][$nestedMatch[2]] = $value;

                continue;
            }

            $topMatch = $unicodeLine->match('/^([\w][\w-]*):\s*(.*)$/');
            if ([] !== $topMatch) {
                $key = $topMatch[1];
                $value = (new UnicodeString($topMatch[2]))->trim()->trim('"\'')->toString();

                if ('' === $value) {
                    $currentKey = $key;
                    $result[$key] = [];
                } else {
                    $currentKey = null;
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}

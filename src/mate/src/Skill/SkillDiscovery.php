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

use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Discovery\PathGuard;
use Symfony\AI\Mate\Exception\SkillCollisionException;
use Symfony\AI\Mate\Skill\Model\DiscoveredSkill;

/**
 * Enumerates the skills declared by extensions and the root project.
 *
 * A package points at a directory via extra.ai-mate.skills; every immediate subdirectory that
 * contains a SKILL.md file is one skill. The original skill name is the front matter "name" and
 * must equal the source directory name. Skills that fail the basic validity guard are skipped
 * with a warning; two packages resolving to the same installed name is a hard error.
 *
 * @phpstan-import-type ExtensionData from ComposerExtensionDiscovery
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillDiscovery
{
    public function __construct(
        private string $rootDir,
        private SkillFrontmatter $frontmatter,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, ExtensionData> $extensions keyed by package name, "_custom" for the root project
     *
     * @return list<DiscoveredSkill>
     */
    public function discover(array $extensions): array
    {
        $skills = [];
        $installedNames = [];

        foreach ($extensions as $owner => $data) {
            foreach ($data['skills'] ?? [] as $skillsDir) {
                foreach ($this->discoverOwnerSkills($owner, $skillsDir) as $skill) {
                    if (isset($installedNames[$skill->installedName])) {
                        throw new SkillCollisionException(\sprintf('Skill name collision: packages "%s" and "%s" both provide the installed skill "%s". Rename the skill in one of the packages.', $installedNames[$skill->installedName], $skill->package, $skill->installedName));
                    }

                    $installedNames[$skill->installedName] = $skill->package;
                    $skills[] = $skill;
                }
            }
        }

        return $skills;
    }

    /**
     * @return list<DiscoveredSkill>
     */
    private function discoverOwnerSkills(string $owner, string $skillsDir): array
    {
        $absoluteSkillsDir = $this->rootDir.'/'.ltrim($skillsDir, '/');
        if (!is_dir($absoluteSkillsDir)) {
            return [];
        }

        $entries = scandir($absoluteSkillsDir);
        if (false === $entries) {
            return [];
        }

        $skills = [];
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $skillPath = $absoluteSkillsDir.'/'.$entry;
            if (!is_dir($skillPath)) {
                continue;
            }

            $skill = $this->readSkill($owner, $skillsDir, $entry, $skillPath);
            if (null !== $skill) {
                $skills[] = $skill;
            }
        }

        return $skills;
    }

    private function readSkill(string $owner, string $skillsDir, string $dirName, string $skillPath): ?DiscoveredSkill
    {
        $skillFile = $skillPath.'/SKILL.md';
        if (!is_file($skillFile)) {
            return null;
        }

        $content = file_get_contents($skillFile);
        if (false === $content) {
            $this->logger->warning('Failed to read SKILL.md', [
                'owner' => $owner,
                'path' => $skillFile,
            ]);

            return null;
        }

        $frontmatter = $this->frontmatter->parse($content);
        if (null === $frontmatter) {
            $this->logger->warning('Skipping skill with unparsable front matter', [
                'owner' => $owner,
                'path' => $skillFile,
            ]);

            return null;
        }

        $name = $frontmatter['name'] ?? '';
        $description = $frontmatter['description'] ?? '';
        if ('' === $name || '' === $description) {
            $this->logger->warning('Skipping skill missing "name" or "description" in front matter', [
                'owner' => $owner,
                'path' => $skillFile,
            ]);

            return null;
        }

        if ($name !== $dirName) {
            $this->logger->warning('Skipping skill whose front matter name does not match its directory', [
                'owner' => $owner,
                'directory' => $dirName,
                'name' => $name,
            ]);

            return null;
        }

        if (PathGuard::hasTraversal($name)) {
            $this->logger->warning('Skipping skill with invalid name', [
                'owner' => $owner,
                'name' => $name,
            ]);

            return null;
        }

        $source = rtrim($skillsDir, '/').'/'.$dirName;

        return new DiscoveredSkill(
            package: $owner,
            originalName: $name,
            installedName: 'mate-'.$name,
            source: $source,
            absolutePath: $skillPath,
            description: $description,
        );
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Service;

use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Discovery\PathGuard;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Installs extension-provided Agent Skills (SKILL.md folders) onto the filesystem
 * so coding agents can use them, until MCP can serve skills directly.
 *
 * Skills land in a single source-of-truth directory (default .agents/skills, read by
 * Codex/OpenCode/Copilot) and are symlinked into per-agent mirror directories
 * (default .claude/skills for Claude Code, which does not read .agents/skills).
 *
 * @phpstan-import-type ExtensionData from ComposerExtensionDiscovery
 *
 * @phpstan-type InstallResult array{
 *     installed: string[],
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsInstaller
{
    /**
     * @param array<string, string> $skillMirrors map of agent label to project-relative mirror dir (always symlinked)
     */
    public function __construct(
        private string $rootDir,
        private LoggerInterface $logger,
        private Filesystem $filesystem,
        private string $skillsDir,
        private array $skillMirrors,
    ) {
    }

    /**
     * Installed skills are placed under a "mate-" prefixed directory name (e.g. "mate-demo-skill")
     * to avoid clashing with skills the user maintains from other sources.
     *
     * @param array<string, ExtensionData> $extensions map of package name to extension data; entries without skills are skipped
     *
     * @return InstallResult
     */
    public function install(array $extensions): array
    {
        $result = [
            'installed' => [],
        ];

        $sourceRoot = $this->rootDir.'/'.trim($this->skillsDir, '/');
        $installedDirs = [];

        foreach ($extensions as $data) {
            if ([] === $data['skills']) {
                continue;
            }

            foreach ($data['skills'] as $skillsRelDir) {
                $skillsPath = $this->rootDir.'/'.trim($skillsRelDir, '/');
                foreach ($this->findSkills($skillsPath) as $name => $skillSource) {
                    $dirName = 'mate-'.$name;

                    // First writer wins on cross-extension name collisions.
                    if (\in_array($dirName, $installedDirs, true)) {
                        $this->logger->warning('Duplicate skill name; keeping the first occurrence', ['skill' => $name]);
                        continue;
                    }

                    $target = $sourceRoot.'/'.$dirName;
                    $relativeTarget = rtrim($this->filesystem->makePathRelative($skillSource, $sourceRoot), '/');

                    if (is_link($target)) {
                        if (file_exists($target) && readlink($target) === $relativeTarget) {
                            // Correct, resolvable link already in place.
                            $installedDirs[] = $dirName;
                            continue;
                        }

                        // A wrong or dangling link we own — repair it below.
                        $this->filesystem->remove($target);
                    } elseif (file_exists($target)) {
                        // A real file or directory we do not own — leave it untouched.
                        $installedDirs[] = $dirName;
                        continue;
                    }

                    $this->placeSkill($skillSource, $target);
                    $result['installed'][] = $dirName;
                    $installedDirs[] = $dirName;
                }
            }
        }

        // Drop Mate-managed links whose skill is no longer discovered (extension/skills removed).
        $this->pruneStale($installedDirs, $sourceRoot);

        foreach ($this->skillMirrors as $mirrorDir) {
            $this->refreshMirror($installedDirs, $mirrorDir);
            $this->pruneStale($installedDirs, $this->rootDir.'/'.trim($mirrorDir, '/'));
        }

        return $result;
    }

    /**
     * Find immediate subdirectories that contain a SKILL.md file.
     *
     * @return array<string, string> map of skill name to absolute source directory
     */
    private function findSkills(string $skillsPath): array
    {
        if (!is_dir($skillsPath)) {
            return [];
        }

        $entries = scandir($skillsPath);
        if (false === $entries) {
            return [];
        }

        $skills = [];
        foreach ($entries as $entry) {
            // "." is scandir's self-reference; PathGuard::hasTraversal() rejects ".." (and any
            // other traversal) so handling stays consistent with discovery.
            if ('.' === $entry || PathGuard::hasTraversal($entry)) {
                continue;
            }

            $skillDir = $skillsPath.'/'.$entry;
            if (is_link($skillDir) || !is_dir($skillDir) || !is_file($skillDir.'/SKILL.md')) {
                continue;
            }

            // A distributed skill is plain files (markdown, scripts, references). Any symlink in the
            // tree would be exposed verbatim through the installed link, letting a malicious package
            // surface files outside its own directory (e.g. a "secrets" entry pointing at ~/.ssh) to
            // the coding agent. Reject such skills wholesale.
            if ($this->containsSymlink($skillDir)) {
                $this->logger->warning('Skipping skill containing symlinks', ['skill' => $entry]);
                continue;
            }

            $skills[$entry] = $skillDir;
        }

        return $skills;
    }

    /**
     * Recursively report whether a directory tree contains any symlink, without following links.
     */
    private function containsSymlink(string $dir): bool
    {
        $entries = scandir($dir);
        if (false === $entries) {
            // Unreadable directory; treat as unsafe rather than silently installing it.
            return true;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || PathGuard::hasTraversal($entry)) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (is_link($path)) {
                return true;
            }

            if (is_dir($path) && $this->containsSymlink($path)) {
                return true;
            }
        }

        return false;
    }

    private function placeSkill(string $source, string $target): void
    {
        $parent = \dirname($target);
        if (!is_dir($parent)) {
            $this->filesystem->mkdir($parent);
        }

        $this->filesystem->symlink(rtrim($this->filesystem->makePathRelative($source, $parent), '/'), $target);
    }

    /**
     * Create/refresh always-symlink mirror entries pointing at the source-of-truth skills.
     *
     * @param string[] $skillDirs prefixed skill directory names (e.g. "mate-demo-skill")
     */
    private function refreshMirror(array $skillDirs, string $mirrorDir): void
    {
        $sourceRoot = $this->rootDir.'/'.trim($this->skillsDir, '/');
        $mirrorRoot = $this->rootDir.'/'.trim($mirrorDir, '/');

        foreach ($skillDirs as $dirName) {
            $sourceSkill = $sourceRoot.'/'.$dirName;
            if (!is_dir($sourceSkill)) {
                continue;
            }

            $mirrorSkill = $mirrorRoot.'/'.$dirName;
            $relativeTarget = rtrim($this->filesystem->makePathRelative($sourceSkill, $mirrorRoot), '/');

            if (is_link($mirrorSkill)) {
                if (readlink($mirrorSkill) === $relativeTarget) {
                    continue;
                }

                // Repair a wrong or dangling link; the mirror is fully managed by Mate.
                $this->filesystem->remove($mirrorSkill);
            } elseif (file_exists($mirrorSkill)) {
                // A real file or directory we do not own — leave it untouched.
                continue;
            }

            if (!is_dir($mirrorRoot)) {
                $this->filesystem->mkdir($mirrorRoot);
            }

            $this->filesystem->symlink($relativeTarget, $mirrorSkill);
        }
    }

    /**
     * Remove Mate-managed skill symlinks under $root that are no longer installed, so coding agents
     * do not keep seeing dangling skills after an extension (or its skills directory) is removed.
     *
     * Only symlinks are removed; real files or directories a user placed are never touched.
     *
     * @param string[] $installedDirs prefixed skill directory names currently installed
     */
    private function pruneStale(array $installedDirs, string $root): void
    {
        if (!is_dir($root)) {
            return;
        }

        $entries = scandir($root);
        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || PathGuard::hasTraversal($entry)) {
                continue;
            }

            if (!str_starts_with($entry, 'mate-') || \in_array($entry, $installedDirs, true)) {
                continue;
            }

            $path = $root.'/'.$entry;
            if (is_link($path)) {
                $this->filesystem->remove($path);
            }
        }
    }
}

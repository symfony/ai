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

use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Skill\Model\DiscoveredSkill;
use Symfony\AI\Mate\Skill\Model\SkillInstallResult;
use Symfony\AI\Mate\Skill\Model\SkillStatus;

/**
 * Entry point the skills:* commands work against.
 *
 * Wraps discovery, state and installation so a command depends on one collaborator instead of five,
 * and owns the cross-referencing of intent, recorded facts and filesystem reality.
 *
 * @phpstan-import-type SkillState from SkillStateRepository
 * @phpstan-import-type SkillIssue from SkillStatus
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillManager
{
    public function __construct(
        private string $rootDir,
        private ComposerExtensionDiscovery $extensionDiscovery,
        private SkillDiscovery $skillDiscovery,
        private SkillStateRepository $repository,
        private SkillInstaller $installer,
        private SkillContentHasher $hasher,
        private SkillFrontmatter $frontmatter,
    ) {
    }

    /**
     * @return list<DiscoveredSkill>
     */
    public function discover(): array
    {
        $extensions = $this->extensionDiscovery->discover();
        $extensions['_custom'] = $this->extensionDiscovery->discoverRootProject();

        return $this->skillDiscovery->discover($extensions);
    }

    public function reinstall(): SkillInstallResult
    {
        return $this->installer->install($this->discover());
    }

    /**
     * @return list<string>
     */
    public function pruneStrays(bool $dryRun): array
    {
        return $this->installer->pruneStrays($dryRun);
    }

    /**
     * @return list<SkillStatus>
     */
    public function status(): array
    {
        $discovered = [];
        foreach ($this->discover() as $skill) {
            $discovered[$skill->package][$skill->originalName] = $skill;
        }

        $statuses = [];
        foreach ($this->repository->read() as $package => $config) {
            foreach ($config['skills'] ?? [] as $name => $state) {
                $statuses[] = $this->buildStatus(
                    $package,
                    $name,
                    $state,
                    $config['enabled'],
                    $discovered[$package][$name] ?? null,
                );
                unset($discovered[$package][$name]);
            }
        }

        // Declared but not yet recorded: discovery ran without an install (or the file was hand-edited).
        foreach ($discovered as $package => $skills) {
            foreach ($skills as $name => $skill) {
                $statuses[] = $this->buildStatus($package, $name, ['enabled' => true, 'mode' => 'managed'], true, $skill);
            }
        }

        usort($statuses, static fn (SkillStatus $a, SkillStatus $b): int => $a->installedName <=> $b->installedName);

        return $statuses;
    }

    /**
     * @param SkillState $state
     */
    private function buildStatus(string $package, string $name, array $state, bool $ownerEnabled, ?DiscoveredSkill $skill): SkillStatus
    {
        $installedName = 'mate-'.$name;
        $enabled = $ownerEnabled && $state['enabled'];
        $recordedState = $state['state'] ?? 'none';

        $issues = $this->collectIssues($name, $installedName, $state, $enabled, $skill);

        $source = $state['source'] ?? null;
        if (null === $source) {
            $source = null !== $skill ? $skill->source : '-';
        }

        return new SkillStatus(
            installedName: $installedName,
            originalName: $name,
            package: $package,
            enabled: $enabled,
            mode: $state['mode'],
            state: $recordedState,
            source: $source,
            status: $this->resolveStatus($enabled, $recordedState, $issues),
            issues: $issues,
        );
    }

    /**
     * @param SkillState $state
     *
     * @return list<SkillIssue>
     */
    private function collectIssues(string $name, string $installedName, array $state, bool $enabled, ?DiscoveredSkill $skill): array
    {
        $issues = [];
        $recordedState = $state['state'] ?? null;

        if (null === $recordedState) {
            $issues[] = ['level' => 'warning', 'message' => 'Declared but never installed; run "mate skills:install".'];

            return $issues;
        }

        $expectedState = $enabled ? $state['mode'] : 'disabled';
        if ($recordedState !== $expectedState) {
            $issues[] = ['level' => 'error', 'message' => \sprintf('Recorded state "%s" does not match intent "%s"; run "mate skills:install".', $recordedState, $expectedState)];
        }

        $agentsTarget = $this->rootDir.'/'.SkillInstaller::AGENTS_SKILLS_DIR.'/'.$installedName;
        $claudeTarget = $this->rootDir.'/'.SkillInstaller::CLAUDE_SKILLS_DIR.'/'.$installedName;

        if ('disabled' === $recordedState) {
            if (is_link($agentsTarget) || file_exists($agentsTarget) || is_link($claudeTarget) || file_exists($claudeTarget)) {
                $issues[] = ['level' => 'error', 'message' => 'Skill is disabled but a generated folder still exists; run "mate skills:prune".'];
            }

            return $issues;
        }

        if ('override' === $recordedState && !is_file($this->rootDir.'/mate/skills/'.$name.'/SKILL.md')) {
            $issues[] = ['level' => 'error', 'message' => \sprintf('Overridden skill has no copy at mate/skills/%s/SKILL.md.', $name)];
        }

        foreach ($state['targets'] ?? [] as $target) {
            $path = $this->rootDir.'/'.$target;
            if (!is_link($path) && !is_dir($path)) {
                $issues[] = ['level' => 'error', 'message' => \sprintf('Missing generated folder "%s"; run "mate skills:install".', $target)];
            }
        }

        $issues = array_merge($issues, $this->collectMirrorIssues($installedName, $claudeTarget));
        $issues = array_merge($issues, $this->collectContentIssues($installedName, $state, $agentsTarget, $skill));

        return $issues;
    }

    /**
     * @return list<SkillIssue>
     */
    private function collectMirrorIssues(string $installedName, string $claudeTarget): array
    {
        if (is_link($claudeTarget)) {
            $expected = '../../'.SkillInstaller::AGENTS_SKILLS_DIR.'/'.$installedName;
            $actual = readlink($claudeTarget);
            if ($actual !== $expected && realpath($claudeTarget) !== realpath(\dirname($claudeTarget).'/'.$expected)) {
                return [['level' => 'error', 'message' => \sprintf('Mirror ".claude/skills/%s" points at "%s" instead of the .agents copy.', $installedName, false === $actual ? '?' : $actual)]];
            }

            return [];
        }

        if (is_dir($claudeTarget)) {
            return [['level' => 'warning', 'message' => \sprintf('Mirror ".claude/skills/%s" is a copy because symlinks are unavailable on this filesystem.', $installedName)]];
        }

        return [];
    }

    /**
     * @param SkillState $state
     *
     * @return list<SkillIssue>
     */
    private function collectContentIssues(string $installedName, array $state, string $agentsTarget, ?DiscoveredSkill $skill): array
    {
        if (!is_dir($agentsTarget)) {
            return [];
        }

        $issues = [];

        $installedHash = $this->hasher->hash($agentsTarget);
        if (null !== ($state['hash'] ?? null) && $installedHash !== $state['hash']) {
            $issues[] = ['level' => 'error', 'message' => 'Generated content was modified by hand; run "mate skills:install" to restore it.'];
        }

        $sourceDir = 'override' === ($state['state'] ?? null)
            ? $this->rootDir.'/mate/skills/'.substr($installedName, 5)
            : $skill?->absolutePath;

        if (null !== $sourceDir && is_dir($sourceDir)) {
            $sourceHash = $this->hasher->hash($sourceDir);
            if (null !== ($state['source_hash'] ?? null) && $sourceHash !== $state['source_hash']) {
                $issues[] = ['level' => 'warning', 'message' => 'Source changed since the last install; run "mate skills:install".'];
            }
        }

        $skillFile = $agentsTarget.'/SKILL.md';
        $content = is_file($skillFile) ? file_get_contents($skillFile) : false;
        if (false === $content) {
            $issues[] = ['level' => 'error', 'message' => 'Installed skill has no readable SKILL.md.'];

            return $issues;
        }

        $frontmatter = $this->frontmatter->parse($content);
        if (null === $frontmatter || ($frontmatter['name'] ?? null) !== $installedName) {
            $issues[] = ['level' => 'error', 'message' => \sprintf('Installed SKILL.md does not declare "name: %s".', $installedName)];
        }

        return $issues;
    }

    /**
     * @param list<SkillIssue> $issues
     *
     * @return 'ok'|'disabled'|'not installed'|'stale'|'broken'
     */
    private function resolveStatus(bool $enabled, string $recordedState, array $issues): string
    {
        foreach ($issues as $issue) {
            if ('error' === $issue['level']) {
                return 'broken';
            }
        }

        if (!$enabled) {
            return 'disabled';
        }

        if ('none' === $recordedState) {
            return 'not installed';
        }

        if ([] !== $issues) {
            return 'stale';
        }

        return 'ok';
    }
}

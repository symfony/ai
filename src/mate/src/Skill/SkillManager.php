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
use Symfony\AI\Mate\Discovery\PathGuard;
use Symfony\AI\Mate\Exception\AmbiguousSkillException;
use Symfony\AI\Mate\Exception\RuntimeException;
use Symfony\AI\Mate\Exception\SkillNotFoundException;
use Symfony\AI\Mate\Skill\Model\DiscoveredSkill;
use Symfony\AI\Mate\Skill\Model\SkillInstallResult;
use Symfony\AI\Mate\Skill\Model\SkillStatus;
use Symfony\Component\Filesystem\Filesystem;

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
    /**
     * Below this, a description cannot carry both what the skill does and when it applies.
     */
    private const MIN_DESCRIPTION_LENGTH = 40;

    /**
     * Words a description uses to name the situation it applies to.
     *
     * Deliberately generous: the check is a nudge, not a gate, so "Use this for deploying" passes
     * on the same footing as "Use when deploying".
     */
    private const USAGE_CUE_PATTERN = '/\b(when|whenever|if|before|after|while|during|unless|once)\b|\buse (this|it)\b/i';

    public function __construct(
        private string $rootDir,
        private ComposerExtensionDiscovery $extensionDiscovery,
        private SkillDiscovery $skillDiscovery,
        private SkillStateRepository $repository,
        private SkillInstaller $installer,
        private SkillContentHasher $hasher,
        private SkillFrontmatter $frontmatter,
        private Filesystem $filesystem,
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

    public function reinstall(bool $dryRun = false): SkillInstallResult
    {
        return $this->installer->install($this->discover(), $dryRun);
    }

    /**
     * @return list<string>
     */
    public function pruneStrays(bool $dryRun): array
    {
        return $this->installer->pruneStrays($dryRun);
    }

    /**
     * Resolves an installed ("mate-foo") or original ("foo") name to the skill that owns it.
     *
     * Resolution runs against the recorded state, not discovery, so a skill stays addressable while
     * its package is temporarily absent.
     *
     * @return array{package: string, name: string}
     *
     * @throws SkillNotFoundException  when no recorded skill matches
     * @throws AmbiguousSkillException when more than one package owns the name
     */
    public function resolve(string $input): array
    {
        $matches = $this->repository->findAll($input);

        if ([] === $matches) {
            throw new SkillNotFoundException(\sprintf('Unknown skill "%s". Run "mate skills:list" to see what is available.', $input));
        }

        if (\count($matches) > 1) {
            $packages = array_map(static fn (array $match): string => $match['package'], $matches);

            throw new AmbiguousSkillException(\sprintf('Skill "%s" is provided by more than one package (%s).', $input, implode(', ', $packages)));
        }

        $name = $matches[0]['name'];
        if (PathGuard::hasTraversal($name)) {
            throw new SkillNotFoundException(\sprintf('Skill name "%s" is not a valid directory name.', $name));
        }

        return ['package' => $matches[0]['package'], 'name' => $name];
    }

    /**
     * @param 'managed'|'override' $mode
     */
    public function setMode(string $package, string $name, string $mode): void
    {
        $this->repository->setMode($package, $name, $mode);
    }

    public function setEnabled(string $package, string $name, bool $enabled): void
    {
        $this->repository->setEnabled($package, $name, $enabled);
    }

    /**
     * Reads the recorded "enabled" flag of a single skill.
     *
     * Deliberately not SkillStatus::$enabled: that one is the effective value, false as soon as the
     * owning extension is disabled. A command that writes this flag has to compare against the flag
     * itself, or it would call a skill "already disabled" when only its extension is.
     *
     * @return bool|null null when the package records no such skill
     */
    public function isEnabled(string $package, string $name): ?bool
    {
        foreach ($this->repository->findAll($name) as $match) {
            if ($match['package'] === $package) {
                return $match['state']['enabled'];
            }
        }

        return null;
    }

    public function overrideCopyPath(string $name): string
    {
        return SkillInstaller::OVERRIDE_SKILLS_DIR.'/'.$name;
    }

    /**
     * Copies the package's version of a skill into mate/skills/<name>/ for the user to own.
     *
     * The copy is taken from the declared source rather than the generated folder, so it keeps the
     * original frontmatter name; the installed name is applied at build time as for any other skill.
     *
     * @return string the created path, relative to the project root
     */
    public function createOverrideCopy(string $package, string $name, bool $force): string
    {
        $target = $this->rootDir.'/'.$this->overrideCopyPath($name);

        if (is_dir($target) && !$force) {
            throw new RuntimeException(\sprintf('"%s" already exists. Pass --force to replace it.', $this->overrideCopyPath($name)));
        }

        $skill = $this->findDiscovered($package, $name);
        if (null === $skill) {
            throw new RuntimeException(\sprintf('Skill "%s" is not currently provided by "%s", so there is nothing to copy.', $name, $package));
        }

        $this->filesystem->remove($target);
        $this->filesystem->mirror($skill->absolutePath, $target);

        return $this->overrideCopyPath($name);
    }

    public function removeOverrideCopy(string $name): bool
    {
        $target = $this->rootDir.'/'.$this->overrideCopyPath($name);
        if (!is_dir($target)) {
            return false;
        }

        $this->filesystem->remove($target);

        return true;
    }

    public function hasOverrideCopy(string $name): bool
    {
        return is_dir($this->rootDir.'/'.$this->overrideCopyPath($name));
    }

    /**
     * @return list<SkillStatus>
     */
    public function statusFor(string $installedOrOriginalName): array
    {
        return array_values(array_filter(
            $this->status(),
            static fn (SkillStatus $status): bool => $status->installedName === $installedOrOriginalName
                || $status->originalName === $installedOrOriginalName,
        ));
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

    private function findDiscovered(string $package, string $name): ?DiscoveredSkill
    {
        foreach ($this->discover() as $skill) {
            if ($skill->package === $package && $skill->originalName === $name) {
                return $skill;
            }
        }

        return null;
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

        if ('override' === $recordedState && !is_file($this->rootDir.'/'.$this->overrideCopyPath($name).'/SKILL.md')) {
            $issues[] = ['level' => 'error', 'message' => \sprintf('Overridden skill has no copy at %s/SKILL.md.', $this->overrideCopyPath($name))];
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
        $state = $this->installer->mirrorState($installedName);

        if ('mispointed' === $state) {
            $actual = readlink($claudeTarget);

            return [['level' => 'error', 'message' => \sprintf('Mirror ".claude/skills/%s" points at "%s" instead of the .agents copy; run "mate skills:install".', $installedName, false === $actual ? '?' : $actual)]];
        }

        if ('copied' === $state) {
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
            ? $this->rootDir.'/'.$this->overrideCopyPath(substr($installedName, 5))
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

        return array_merge(
            $issues,
            $this->collectDescriptionIssues($frontmatter['description'] ?? ''),
            $this->collectReferenceIssues($agentsTarget, $content),
        );
    }

    /**
     * Checks the description an agent decides from, before it ever opens the skill.
     *
     * Both findings are suggestions, and deliberately never fail a run: a description that never
     * says when the skill applies makes the agent guess, but length and wording are heuristics
     * tuned for English prose, and a correct description can miss both and still read well.
     *
     * @return list<SkillIssue>
     */
    private function collectDescriptionIssues(string $description): array
    {
        if ('' === $description) {
            return [];
        }

        $issues = [];

        if (mb_strlen($description) < self::MIN_DESCRIPTION_LENGTH) {
            $issues[] = ['level' => 'suggestion', 'message' => \sprintf('Description is only %d characters long; an agent picks the skill from it alone.', mb_strlen($description))];
        }

        if (1 !== preg_match(self::USAGE_CUE_PATTERN, $description)) {
            $issues[] = ['level' => 'suggestion', 'message' => 'Description does not say when to use the skill; name the situation it applies to, as in "Use when the tests fail" or "Use this for deploying".'];
        }

        return $issues;
    }

    /**
     * Checks that the files SKILL.md links to came along into the installed folder.
     *
     * A skill folder is self-contained by design, so a relative link that resolves to nothing is a
     * dead end for the agent following it. Only inline Markdown links are inspected: a path
     * mentioned in prose is as likely to name a file in the user's project as one in the skill.
     *
     * @return list<SkillIssue>
     */
    private function collectReferenceIssues(string $agentsTarget, string $content): array
    {
        preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $content, $matches);

        /** @var list<string> $missing */
        $missing = [];
        foreach ($matches[1] as $target) {
            $path = explode('#', explode('?', trim($target, '<>'))[0])[0];
            if ('' === $path) {
                continue;
            }

            // Anything addressable on its own: URLs, mail links, and paths anchored at the project root.
            if (str_starts_with($path, '/') || 1 === preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)) {
                continue;
            }

            $path = rawurldecode($path);
            if (!file_exists($agentsTarget.'/'.$path) && !\in_array($path, $missing, true)) {
                $missing[] = $path;
            }
        }

        $issues = [];
        foreach ($missing as $path) {
            $issues[] = ['level' => 'warning', 'message' => \sprintf('SKILL.md links to "%s", which is not part of the installed skill.', $path)];
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

        // Suggestions are advice about the authored content, not drift, so they leave a skill "ok".
        foreach ($issues as $issue) {
            if ('warning' === $issue['level']) {
                return 'stale';
            }
        }

        return 'ok';
    }
}

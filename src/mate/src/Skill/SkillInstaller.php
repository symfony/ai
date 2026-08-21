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
use Symfony\AI\Mate\Skill\Model\DiscoveredSkill;
use Symfony\AI\Mate\Skill\Model\SkillInstallResult;

/**
 * Idempotent reconciler that rebuilds the generated skill folders from source + intent.
 *
 * A managed skill is a real copy, never a symlink into vendor/: the point is that you can read and
 * diff exactly what your agent will load. Only the .claude/skills/ mirror is a relative symlink to
 * the canonical .agents/skills/ copy, so the two can never drift apart; on filesystems without
 * symlink support it falls back to a second copy.
 *
 * Every outcome is recorded back into mate/extensions.php, next to the intent it derives from.
 * Skills whose source vanished are pruned immediately, together with their entry.
 *
 * The generated folders are deliberately not git-ignored: because they are plain copies, committing
 * them makes an upstream skill change visible in review instead of arriving silently.
 *
 * @phpstan-import-type SkillState from SkillStateRepository
 * @phpstan-import-type ExtensionConfigMap from SkillStateRepository
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillInstaller
{
    public const AGENTS_SKILLS_DIR = '.agents/skills';
    public const CLAUDE_SKILLS_DIR = '.claude/skills';

    public function __construct(
        private string $rootDir,
        private SkillStateRepository $repository,
        private SkillFrontmatter $frontmatter,
        private SkillContentHasher $hasher,
        private LinkerInterface $linker,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<DiscoveredSkill> $skills
     */
    public function install(array $skills): SkillInstallResult
    {
        $config = $this->repository->read();

        $discovered = [];
        foreach ($skills as $skill) {
            $discovered[$skill->package][$skill->originalName] = $skill;
        }

        $vanished = $this->dropVanished($config, $discovered);
        $config = $vanished['config'];
        $removed = $vanished['removed'];

        $installed = [];
        $skipped = [];
        $notices = [];
        $states = [];
        $active = [];

        foreach ($skills as $skill) {
            $config = $this->ensureEntry($config, $skill);

            $state = $config[$skill->package]['skills'][$skill->originalName];
            $enabled = $config[$skill->package]['enabled'] && $state['enabled'];

            if (!$enabled) {
                if ($this->removeTargets($skill->installedName, $state['targets'] ?? [])) {
                    $removed[] = $skill->installedName;
                }

                $config[$skill->package]['skills'][$skill->originalName] = array_merge($state, [
                    'state' => 'disabled',
                    'source' => $skill->source,
                    'source_hash' => null,
                    'hash' => null,
                    'targets' => [],
                ]);
                $states[$skill->installedName] = 'disabled';

                continue;
            }

            $override = 'override' === $state['mode'];
            $sourceDir = $override ? $this->overrideSourceDir($skill) : $skill->absolutePath;

            if (!is_dir($sourceDir) || !is_file($sourceDir.'/SKILL.md')) {
                $this->logger->warning('Skipping skill with missing source', [
                    'skill' => $skill->installedName,
                    'source' => $sourceDir,
                    'mode' => $state['mode'],
                ]);
                $skipped[$skill->installedName] = $override
                    ? 'override source missing in mate/skills/'
                    : 'source directory missing';

                continue;
            }

            $wasInstalled = isset($state['state']) && 'disabled' !== $state['state'];

            $build = $this->buildSkill($skill, $sourceDir, $override, $state);
            if (null !== $build['notice']) {
                $notices[] = $build['notice'];
            }

            $config[$skill->package]['skills'][$skill->originalName] = array_merge($state, $build['facts']);

            $states[$skill->installedName] = $override ? 'override' : 'managed';
            $active[] = $skill->installedName;

            if (!$wasInstalled) {
                $installed[] = $skill->installedName;
            }
        }

        $this->repository->write($config);

        $removed = array_merge($removed, $this->pruneStrays(false));
        $removed = array_values(array_unique($removed));
        sort($removed);
        sort($active);

        return new SkillInstallResult($installed, $removed, $skipped, $active, $notices, $states);
    }

    /**
     * Removes generated mate-* folders that no longer belong to an active skill.
     *
     * @return list<string>
     */
    public function pruneStrays(bool $dryRun): array
    {
        $expected = [];
        foreach ($this->repository->read() as $config) {
            foreach ($config['skills'] ?? [] as $state) {
                foreach ($state['targets'] ?? [] as $target) {
                    $expected[$target] = true;
                }
            }
        }

        $strays = [];
        foreach ([self::AGENTS_SKILLS_DIR, self::CLAUDE_SKILLS_DIR] as $baseDir) {
            $absoluteBase = $this->rootDir.'/'.$baseDir;
            if (!is_dir($absoluteBase)) {
                continue;
            }

            $entries = scandir($absoluteBase);
            if (false === $entries) {
                continue;
            }

            foreach ($entries as $entry) {
                if ('.' === $entry || '..' === $entry || !str_starts_with($entry, 'mate-')) {
                    continue;
                }

                if (isset($expected[$baseDir.'/'.$entry])) {
                    continue;
                }

                $strays[$entry] = true;
                if (!$dryRun) {
                    $this->removePath($absoluteBase.'/'.$entry);
                }
            }
        }

        $names = array_keys($strays);
        sort($names);

        return $names;
    }

    /**
     * @param ExtensionConfigMap                            $config
     * @param array<string, array<string, DiscoveredSkill>> $discovered
     *
     * @return array{config: ExtensionConfigMap, removed: list<string>}
     */
    private function dropVanished(array $config, array $discovered): array
    {
        $removed = [];
        foreach ($config as $package => $entry) {
            foreach ($entry['skills'] ?? [] as $name => $state) {
                if (isset($discovered[$package][$name])) {
                    continue;
                }

                if ($this->removeTargets('mate-'.$name, $state['targets'] ?? [])) {
                    $removed[] = 'mate-'.$name;
                }

                unset($config[$package]['skills'][$name]);
            }

            if ([] === ($config[$package]['skills'] ?? null)) {
                unset($config[$package]['skills']);
            }
        }

        return ['config' => $config, 'removed' => $removed];
    }

    /**
     * @param ExtensionConfigMap $config
     *
     * @return ExtensionConfigMap
     */
    private function ensureEntry(array $config, DiscoveredSkill $skill): array
    {
        if (!isset($config[$skill->package])) {
            $config[$skill->package] = ['enabled' => true];
        }

        if (!isset($config[$skill->package]['skills'][$skill->originalName])) {
            $config[$skill->package]['skills'][$skill->originalName] = ['enabled' => true, 'mode' => 'managed'];
        }

        return $config;
    }

    /**
     * @param SkillState $previous
     *
     * @return array{
     *     facts: array{state: 'managed'|'override', source: string, source_hash: string|null, hash: string|null, targets: list<string>},
     *     notice: string|null,
     * }
     */
    private function buildSkill(DiscoveredSkill $skill, string $sourceDir, bool $override, array $previous): array
    {
        $agentsTarget = $this->rootDir.'/'.self::AGENTS_SKILLS_DIR.'/'.$skill->installedName;
        $claudeTarget = $this->rootDir.'/'.self::CLAUDE_SKILLS_DIR.'/'.$skill->installedName;

        $source = $override ? 'mate/skills/'.$skill->originalName : $skill->source;
        $sourceHash = $this->hasher->hash($sourceDir);

        $facts = [
            'state' => $override ? 'override' : 'managed',
            'source' => $source,
            'source_hash' => $sourceHash,
            'targets' => [
                self::AGENTS_SKILLS_DIR.'/'.$skill->installedName,
                self::CLAUDE_SKILLS_DIR.'/'.$skill->installedName,
            ],
        ];

        if ($this->isUpToDate($previous, $sourceHash, $agentsTarget, $claudeTarget)) {
            $facts['hash'] = $previous['hash'] ?? null;

            return ['facts' => $facts, 'notice' => null];
        }

        $this->removePath($agentsTarget);
        $this->copyDirectory($sourceDir, $agentsTarget);
        $this->rewriteSkillName($agentsTarget.'/SKILL.md', $skill->installedName);

        $notice = null;
        if (!$this->linkMirror($claudeTarget, $skill->installedName, $agentsTarget)) {
            $notice = \sprintf('Skill "%s" was mirrored into .claude/skills/ as a copy because symlinks are unavailable.', $skill->installedName);
        }

        $facts['hash'] = $this->hasher->hash($agentsTarget);

        return ['facts' => $facts, 'notice' => $notice];
    }

    /**
     * @param SkillState $previous
     */
    private function isUpToDate(array $previous, ?string $sourceHash, string $agentsTarget, string $claudeTarget): bool
    {
        if (null === $sourceHash || ($previous['source_hash'] ?? null) !== $sourceHash) {
            return false;
        }

        $installedHash = $previous['hash'] ?? null;
        if (null === $installedHash || $this->hasher->hash($agentsTarget) !== $installedHash) {
            return false;
        }

        return $this->mirrorExists($claudeTarget);
    }

    private function mirrorExists(string $mirrorPath): bool
    {
        if (is_link($mirrorPath)) {
            return false !== realpath($mirrorPath);
        }

        return is_dir($mirrorPath);
    }

    private function overrideSourceDir(DiscoveredSkill $skill): string
    {
        return $this->rootDir.'/mate/skills/'.$skill->originalName;
    }

    /**
     * @param list<string> $recordedTargets
     */
    private function removeTargets(string $installedName, array $recordedTargets): bool
    {
        $targets = $recordedTargets;
        $targets[] = self::AGENTS_SKILLS_DIR.'/'.$installedName;
        $targets[] = self::CLAUDE_SKILLS_DIR.'/'.$installedName;

        $anyRemoved = false;
        foreach (array_unique($targets) as $target) {
            $path = $this->rootDir.'/'.$target;
            if (is_link($path) || file_exists($path)) {
                $anyRemoved = true;
            }

            $this->removePath($path);
        }

        return $anyRemoved;
    }

    private function rewriteSkillName(string $skillFile, string $installedName): void
    {
        $content = file_get_contents($skillFile);
        if (false === $content) {
            return;
        }

        $rewritten = $this->frontmatter->rewriteName($content, $installedName);
        if ($rewritten !== $content) {
            file_put_contents($skillFile, $rewritten);
        }
    }

    private function linkMirror(string $mirrorPath, string $installedName, string $agentsTarget): bool
    {
        $this->removePath($mirrorPath);

        $mirrorDir = \dirname($mirrorPath);
        if (!is_dir($mirrorDir)) {
            mkdir($mirrorDir, 0755, true);
        }

        $relativeTarget = '../../'.self::AGENTS_SKILLS_DIR.'/'.$installedName;

        if ($this->linker->link($relativeTarget, $mirrorPath)) {
            return true;
        }

        $this->logger->warning('Failed to create skill mirror symlink; copying the skill into .claude/skills/ instead', [
            'mirror' => $mirrorPath,
            'target' => $relativeTarget,
        ]);

        $this->copyDirectory($agentsTarget, $mirrorPath);

        return false;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        $prefixLength = \strlen($source) + 1;
        foreach ($iterator as $item) {
            \assert($item instanceof \SplFileInfo);

            // isDir() resolves symlinks, so a link inside the source would be dereferenced and copied.
            if ($item->isLink()) {
                continue;
            }

            $target = $destination.'/'.substr($item->getPathname(), $prefixLength);

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }

                continue;
            }

            copy($item->getPathname(), $target);
        }
    }

    private function removePath(string $path): void
    {
        if (is_link($path)) {
            // A Windows directory symlink or junction cannot be unlinked, it has to be removed as a directory.
            if (!@unlink($path) && is_dir($path)) {
                @rmdir($path);
            }

            return;
        }

        if (!file_exists($path)) {
            return;
        }

        if (is_file($path)) {
            unlink($path);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            \assert($item instanceof \SplFileInfo);
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());

                continue;
            }

            rmdir($item->getPathname());
        }

        rmdir($path);
    }
}

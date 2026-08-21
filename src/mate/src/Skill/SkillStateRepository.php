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

use Symfony\AI\Mate\Exception\FileWriteException;

/**
 * Sole reader and writer of mate/extensions.php, the single state file for extensions and skills.
 *
 * Every fact about a skill lives next to the setting it describes: "enabled" and "mode" are the two
 * keys a user may hand-edit, while "state", "source", "source_hash", "hash" and "targets" are
 * rewritten by the installer on every run. The fact keys are only emitted once a skill has actually
 * been installed, so a freshly discovered skill renders as "enabled" + "mode" alone.
 *
 * No other class may include or write this path: ContainerFactory reads it independently to decide
 * which extensions to load, and a torn write would read back as "every extension disabled".
 *
 * @phpstan-type SkillState array{
 *     enabled: bool,
 *     mode: 'managed'|'override',
 *     state?: 'managed'|'override'|'disabled',
 *     source?: string,
 *     source_hash?: string|null,
 *     hash?: string|null,
 *     targets?: list<string>,
 * }
 * @phpstan-type ExtensionConfig array{enabled: bool, skills?: array<string, SkillState>}
 * @phpstan-type ExtensionConfigMap array<string, ExtensionConfig>
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillStateRepository
{
    /**
     * @var ExtensionConfigMap|null
     */
    private ?array $cache = null;

    public function __construct(
        private string $rootDir,
    ) {
    }

    public function path(): string
    {
        return $this->rootDir.'/mate/extensions.php';
    }

    public function exists(): bool
    {
        return file_exists($this->path());
    }

    /**
     * @return ExtensionConfigMap
     */
    public function read(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        return $this->cache = $this->normalize($this->load());
    }

    /**
     * @param ExtensionConfigMap $config
     */
    public function write(array $config): void
    {
        $content = $this->render($config);

        $path = $this->path();
        if (file_exists($path) && file_get_contents($path) === $content) {
            $this->cache = $config;

            return;
        }

        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // ContainerFactory includes this file independently and treats a non-array result as "no
        // extension enabled", so a half-written file must never be observable: write beside the
        // target and swap it in with an atomic rename. Both steps fail loudly — a caller that
        // believes state was persisted when it was not would go on to act on stale intent.
        $temporaryPath = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (false === @file_put_contents($temporaryPath, $content)) {
            @unlink($temporaryPath);

            throw new FileWriteException(\sprintf('Failed to write skill state to "%s".', $temporaryPath));
        }

        if (!@rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new FileWriteException(\sprintf('Failed to move skill state into place at "%s".', $path));
        }

        $this->cache = $config;
    }

    /**
     * Resolves an installed ("mate-foo") or original ("foo") skill name to its single owner.
     *
     * @return array{package: string, name: string, state: SkillState}|null
     */
    public function find(string $installedOrOriginalName): ?array
    {
        $matches = $this->findAll($installedOrOriginalName);
        if (1 !== \count($matches)) {
            return null;
        }

        return $matches[0];
    }

    /**
     * @return list<array{package: string, name: string, state: SkillState}>
     */
    public function findAll(string $installedOrOriginalName): array
    {
        $candidates = [$installedOrOriginalName];
        if (str_starts_with($installedOrOriginalName, 'mate-')) {
            $candidates[] = substr($installedOrOriginalName, 5);
        }

        $matches = [];
        foreach ($this->read() as $package => $config) {
            foreach ($config['skills'] ?? [] as $name => $state) {
                if (\in_array($name, $candidates, true)) {
                    $matches[] = ['package' => $package, 'name' => $name, 'state' => $state];
                }
            }
        }

        return $matches;
    }

    public function ensureEntry(string $package, string $name): void
    {
        $config = $this->read();
        if (isset($config[$package]['skills'][$name])) {
            return;
        }

        if (!isset($config[$package])) {
            $config[$package] = ['enabled' => true];
        }

        $config[$package]['skills'][$name] = ['enabled' => true, 'mode' => 'managed'];
        $this->write($config);
    }

    public function setEnabled(string $package, string $name, bool $enabled): void
    {
        $this->mutate($package, $name, static function (array $state) use ($enabled): array {
            $state['enabled'] = $enabled;

            return $state;
        });
    }

    /**
     * @param 'managed'|'override' $mode
     */
    public function setMode(string $package, string $name, string $mode): void
    {
        $this->mutate($package, $name, static function (array $state) use ($mode): array {
            $state['mode'] = $mode;

            return $state;
        });
    }

    /**
     * @param array{state: 'managed'|'override'|'disabled', source: string, source_hash: string|null, hash: string|null, targets: list<string>} $facts
     */
    public function persistFacts(string $package, string $name, array $facts): void
    {
        $this->mutate($package, $name, static fn (array $state): array => array_merge($state, $facts));
    }

    public function dropEntry(string $package, string $name): void
    {
        $config = $this->read();
        if (!isset($config[$package]['skills'][$name])) {
            return;
        }

        unset($config[$package]['skills'][$name]);
        if ([] === $config[$package]['skills']) {
            unset($config[$package]['skills']);
        }

        $this->write($config);
    }

    /**
     * @param \Closure(SkillState): SkillState $mutator
     */
    private function mutate(string $package, string $name, \Closure $mutator): void
    {
        $this->ensureEntry($package, $name);

        $config = $this->read();
        $config[$package]['skills'][$name] = $mutator($config[$package]['skills'][$name]);

        $this->write($config);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load(): array
    {
        $path = $this->path();
        if (!file_exists($path)) {
            return [];
        }

        $data = include $path;
        if (!\is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $package => $config) {
            if (\is_string($package) && \is_array($config)) {
                $result[$package] = $config;
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $data
     *
     * @return ExtensionConfigMap
     */
    private function normalize(array $data): array
    {
        $normalized = [];
        foreach ($data as $package => $config) {
            $enabled = true;
            if (isset($config['enabled']) && \is_bool($config['enabled'])) {
                $enabled = $config['enabled'];
            }

            $entry = ['enabled' => $enabled];

            $skills = $this->normalizeSkills($config['skills'] ?? null);
            if ([] !== $skills) {
                $entry['skills'] = $skills;
            }

            $normalized[$package] = $entry;
        }

        return $normalized;
    }

    /**
     * @return array<string, SkillState>
     */
    private function normalizeSkills(mixed $skills): array
    {
        if (!\is_array($skills)) {
            return [];
        }

        $normalized = [];
        foreach ($skills as $name => $state) {
            if (!\is_string($name) || !\is_array($state)) {
                continue;
            }

            $normalized[$name] = $this->normalizeSkill($state);
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $state
     *
     * @return SkillState
     */
    private function normalizeSkill(array $state): array
    {
        $enabled = !isset($state['enabled']) || false !== $state['enabled'];

        $mode = 'managed';
        if (isset($state['mode']) && 'override' === $state['mode']) {
            $mode = 'override';
        } elseif (!isset($state['mode']) && isset($state['override']) && true === $state['override']) {
            // Pre-0.13 shape: a boolean "override" flag instead of the "mode" key.
            $mode = 'override';
        }

        $normalized = ['enabled' => $enabled, 'mode' => $mode];

        $installedState = $state['state'] ?? null;
        if (!\in_array($installedState, ['managed', 'override', 'disabled'], true)) {
            return $normalized;
        }

        $source = $state['source'] ?? null;
        if (!\is_string($source)) {
            return $normalized;
        }

        $normalized['state'] = $installedState;
        $normalized['source'] = $source;
        $normalized['source_hash'] = \is_string($state['source_hash'] ?? null) ? $state['source_hash'] : null;
        $normalized['hash'] = \is_string($state['hash'] ?? null) ? $state['hash'] : null;

        $targets = [];
        foreach (\is_array($state['targets'] ?? null) ? $state['targets'] : [] as $target) {
            if (\is_string($target)) {
                $targets[] = $target;
            }
        }
        $normalized['targets'] = $targets;

        return $normalized;
    }

    /**
     * @param ExtensionConfigMap $config
     */
    private function render(array $config): string
    {
        $content = "<?php\n\n";
        $content .= "// Managed by \"mate discover\" and the \"mate skills:*\" commands, which can change\n";
        $content .= "// every setting in here — prefer them over editing by hand. If you do edit: \"enabled\"\n";
        $content .= "// and \"mode\" are kept, every other key is rewritten on the next install.\n\n";
        $content .= "return [\n";

        foreach ($config as $package => $entry) {
            $content .= $this->renderExtension($package, $entry);
        }

        return $content."];\n";
    }

    /**
     * @param ExtensionConfig $entry
     */
    private function renderExtension(string $package, array $entry): string
    {
        $skills = $entry['skills'] ?? [];

        // Package and skill names originate from third-party composer.json files and are written into
        // a PHP file that is later included; var_export() escapes them safely against code injection.
        if ([] === $skills) {
            return \sprintf("    %s => ['enabled' => %s],\n", var_export($package, true), $this->renderBool($entry['enabled']));
        }

        ksort($skills);

        $content = \sprintf("    %s => [\n", var_export($package, true));
        $content .= \sprintf("        'enabled' => %s,\n", $this->renderBool($entry['enabled']));
        $content .= "        'skills' => [\n";

        foreach ($skills as $name => $state) {
            $content .= $this->renderSkill($name, $state);
        }

        $content .= "        ],\n";

        return $content."    ],\n";
    }

    /**
     * @param SkillState $state
     */
    private function renderSkill(string $name, array $state): string
    {
        $content = \sprintf("            %s => [\n", var_export($name, true));
        $content .= \sprintf("                'enabled' => %s,\n", $this->renderBool($state['enabled']));
        $content .= \sprintf("                'mode' => %s,\n", var_export($state['mode'], true));

        if (!isset($state['state'])) {
            return $content."            ],\n";
        }

        $content .= \sprintf("                'state' => %s,\n", var_export($state['state'], true));
        $content .= \sprintf("                'source' => %s,\n", var_export($state['source'] ?? '', true));
        $content .= \sprintf("                'source_hash' => %s,\n", $this->renderNullableString($state['source_hash'] ?? null));
        $content .= \sprintf("                'hash' => %s,\n", $this->renderNullableString($state['hash'] ?? null));

        $targets = $state['targets'] ?? [];
        if ([] === $targets) {
            $content .= "                'targets' => [],\n";

            return $content."            ],\n";
        }

        $content .= "                'targets' => [\n";
        foreach ($targets as $target) {
            $content .= \sprintf("                    %s,\n", var_export($target, true));
        }
        $content .= "                ],\n";

        return $content."            ],\n";
    }

    private function renderBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function renderNullableString(?string $value): string
    {
        if (null === $value) {
            return 'null';
        }

        return var_export($value, true);
    }
}

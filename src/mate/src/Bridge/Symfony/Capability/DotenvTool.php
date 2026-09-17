<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Capability;

use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;

/**
 * Reports which `.env*` files declare a variable and whether it resolves at
 * runtime, without ever putting a raw value in the output.
 *
 * `bin/console debug:dotenv` prints fully resolved, unmasked values, including
 * ones that only resolve because the ambient shell environment happens to
 * carry a real secret (CI variables, a global shell profile, ...) rather than
 * any file in the project. This tool answers the same question Mate's own
 * agent instructions point agents to `debug:dotenv` for, but every value is
 * either omitted (empty) or masked to a length and a first/last character
 * preview, and each variable is labelled with where its runtime value
 * actually came from.
 *
 * @phpstan-type FileEntry array{file: string, exists: bool, considered: bool, parseable: bool|null}
 * @phpstan-type FileCandidate array{file: string, considered: bool}
 * @phpstan-type VariableEntry array{
 *     key: string,
 *     declared_in: list<string>,
 *     resolved: bool,
 *     state: string,
 *     length: int,
 *     preview: string|null,
 *     looks_like_placeholder: bool,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DotenvTool
{
    private const DEFAULT_LIMIT = 200;

    /**
     * Symfony's own default; only "test" is treated as a test environment here since Mate has no
     * access to a project-specific `$testEnvs` argument (only the app's own `bin/console`
     * bootstrap knows that).
     *
     * @var list<string>
     */
    private const TEST_ENVS = ['test'];

    /**
     * Substrings (case-insensitive) that mark a resolved value as a very likely placeholder a
     * developer forgot to replace, rather than a real secret. Best-effort only: a project can
     * always name its actual value something that happens to match one of these.
     *
     * @var list<string>
     */
    private const PLACEHOLDER_PATTERNS = [
        'changeme', 'change_me', 'change-me',
        'your_', 'your-',
        'replace', 'placeholder', 'insert_', 'insert-',
        'xxxx', 'todo', 'fixme', 'dummy', 'sample',
        '<', '>',
    ];

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param string|null $key   Check exactly this variable name, even if it is declared in no project
     *                           `.env*` file (use this to check a variable seen elsewhere, e.g. in a config
     *                           `%env(FOO)%` reference or a suspicious name from a leaked log). Omit to list
     *                           every variable declared across the discovered `.env*` files.
     * @param int         $limit Maximum number of variables to return when listing (ignored when `key` is given)
     */
    #[MateTool(name: 'symfony-dotenv-check', title: 'Symfony Dotenv Check', description: 'Report which .env* files declare each environment variable and whether it resolves to a non-empty value at runtime, distinguishing a value that comes from a project file from one that only resolves because of the ambient shell/CI environment. Never returns a raw value: only a masked length+first/last-character preview and a placeholder guess. Pass "key" to check one specific variable name directly, even one declared in no file.')]
    public function check(?string $key = null, int $limit = self::DEFAULT_LIMIT): string
    {
        $appEnv = $this->resolveAppEnv();
        $candidates = $this->candidateFiles($appEnv);

        $files = [];
        $declaredIn = [];
        $valuesByKeyAndFile = [];
        $winningFileValue = [];

        foreach ($candidates as ['file' => $relativeFile, 'considered' => $considered]) {
            $path = $this->projectDir.'/'.$relativeFile;
            $exists = is_file($path);
            $parseable = null;

            if ($exists && $considered) {
                $parseable = true;

                try {
                    $parsed = (new Dotenv())->parse((string) file_get_contents($path), $path);
                } catch (FormatException) {
                    $parsed = [];
                    $parseable = false;
                }

                foreach ($parsed as $varKey => $varValue) {
                    $declaredIn[$varKey][] = $relativeFile;
                    $valuesByKeyAndFile[$varKey][$relativeFile] = $varValue;
                    // Later files take precedence, mirroring Symfony's own Dotenv::populate() order.
                    $winningFileValue[$varKey] = $varValue;
                }
            }

            $files[] = ['file' => $relativeFile, 'exists' => $exists, 'considered' => $considered, 'parseable' => $parseable];
        }

        if (null !== $key) {
            $keysToReport = [$key];
            $truncated = false;
            $count = 1;
        } else {
            $keysToReport = array_keys($declaredIn);
            sort($keysToReport);
            $count = \count($keysToReport);
            $truncated = $limit > 0 && $count > $limit;
            if ($truncated) {
                $keysToReport = \array_slice($keysToReport, 0, $limit);
            }
        }

        $variables = [];
        foreach ($keysToReport as $varKey) {
            $variables[] = $this->describeVariable(
                $varKey,
                $declaredIn[$varKey] ?? [],
                $valuesByKeyAndFile[$varKey] ?? [],
                $winningFileValue[$varKey] ?? null,
            );
        }

        return ResponseEncoder::encode([
            'app_env' => $appEnv,
            'files' => $files,
            'variables' => $variables,
            'count' => $count,
            'truncated' => $truncated,
        ]);
    }

    /**
     * @param list<string>          $declaredInFiles
     * @param array<string, string> $valuesByFile
     *
     * @return VariableEntry
     */
    private function describeVariable(string $key, array $declaredInFiles, array $valuesByFile, ?string $winningFileValue): array
    {
        $realValue = $this->resolveRuntimeValue($key);
        $resolved = null !== $realValue;

        if ($resolved) {
            if ([] === $declaredInFiles) {
                $state = 'ambient_only';
            } elseif (\in_array($realValue, $valuesByFile, true)) {
                $state = 'file';
            } else {
                $state = 'ambient_override';
            }
        } else {
            if ([] === $declaredInFiles) {
                $state = 'not_set';
            } elseif ('' === $winningFileValue) {
                $state = 'declared_empty_in_file';
            } else {
                $state = 'declared_not_resolved_in_this_process';
            }
        }

        $entry = [
            'key' => $key,
            'declared_in' => $declaredInFiles,
            'resolved' => $resolved,
            'state' => $state,
            'length' => 0,
            'preview' => null,
            'looks_like_placeholder' => false,
        ];

        if ($resolved) {
            $entry['length'] = \strlen($realValue);
            $entry['preview'] = $this->maskPreview($realValue);
            $entry['looks_like_placeholder'] = $this->looksLikePlaceholder($realValue);
        }

        return $entry;
    }

    private function resolveAppEnv(): string
    {
        $value = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV');

        if (\is_string($value) && '' !== $value) {
            return $value;
        }

        return 'dev';
    }

    /**
     * Mirrors `Symfony\Component\Dotenv\Dotenv::loadEnv()`'s own file discovery: `.env` (or
     * `.env.dist` as a fallback when `.env` is absent), then `.env.local` (skipped entirely in a
     * test environment, matching Symfony's own rule so tests stay deterministic), then
     * `.env.$APP_ENV`, then `.env.$APP_ENV.local`.
     *
     * @return list<FileCandidate>
     */
    private function candidateFiles(string $appEnv): array
    {
        $base = '.env';
        if (!is_file($this->projectDir.'/.env') && is_file($this->projectDir.'/.env.dist')) {
            $base = '.env.dist';
        }

        // `.env.local` is listed for transparency even when it is not consulted (skipped in a
        // test environment, matching Symfony's own rule so tests stay deterministic), so a
        // developer can see it exists on disk without wondering why its declarations never show up.
        return [
            ['file' => $base, 'considered' => true],
            ['file' => '.env.local', 'considered' => !\in_array($appEnv, self::TEST_ENVS, true)],
            ['file' => '.env.'.$appEnv, 'considered' => true],
            ['file' => '.env.'.$appEnv.'.local', 'considered' => true],
        ];
    }

    /**
     * Reads the variable exactly as Mate's own CLI process sees it right now ($_SERVER, then
     * $_ENV, then getenv()) — a measured fact about this process, not an inference about what the
     * application would resolve if it booted its own Dotenv. This is the same process boundary
     * `server-info` already documents: the PHP running Mate's CLI can differ from the one serving
     * the app, so a variable declared only in a file can show as unresolved here even though the
     * real app resolves it fine once its own bootstrap loads that file.
     */
    private function resolveRuntimeValue(string $key): ?string
    {
        foreach ([$_SERVER, $_ENV] as $bag) {
            if (isset($bag[$key]) && \is_string($bag[$key]) && '' !== $bag[$key]) {
                return $bag[$key];
            }
        }

        $value = getenv($key);

        return (false !== $value && '' !== $value) ? $value : null;
    }

    /**
     * Never reveals enough to reconstruct the value: a fixed 3-character mask between the first
     * and last character for anything long enough that revealing two characters is a negligible
     * leak, and a fixed, length-independent mask for anything so short that the first and last
     * character would be most or all of the value. The `length` field already reports the real
     * length as a separate, clearly-labelled number, so the preview itself never needs to encode
     * length by its own width.
     */
    private function maskPreview(string $value): string
    {
        $length = \strlen($value);

        if ($length <= 2) {
            return '**';
        }

        return $value[0].'***'.$value[$length - 1];
    }

    /**
     * Best-effort signal only, based on the value's content, never its key name: a hit here does
     * not reveal any character beyond what {@see maskPreview()} already shows.
     */
    private function looksLikePlaceholder(string $value): bool
    {
        $lower = strtolower($value);

        foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}

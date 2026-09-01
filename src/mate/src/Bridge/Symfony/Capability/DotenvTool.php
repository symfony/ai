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
 * Reports which `.env*` files declare a variable, and where its runtime value
 * actually comes from, without ever putting a raw value in the output.
 *
 * A safe replacement for `bin/console debug:dotenv`, which prints fully
 * resolved values unmasked, including ones that only resolve because the
 * ambient shell/CI environment happens to carry a real secret.
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
     * Only "test" is treated as a test environment: Mate has no access to a project's own
     * `$testEnvs` argument, only the app's `bin/console` bootstrap knows that.
     *
     * @var list<string>
     */
    private const TEST_ENVS = ['test'];

    /**
     * Case-insensitive substrings marking a value as a likely-forgotten placeholder rather than a
     * real secret. Best-effort: a project can name a real value the same as one of these.
     *
     * @var list<string>
     */
    private const PLACEHOLDER_PATTERNS = [
        'changeme',
        'change_me',
        'change-me',
        'your_',
        'your-',
        'replace',
        'placeholder',
        'insert_',
        'insert-',
        'xxxx',
        'todo',
        'fixme',
        'dummy',
        'sample',
        '<',
        '>',
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
     * @param list<string> $declaredInFiles
     *
     * @return VariableEntry
     */
    private function describeVariable(string $key, array $declaredInFiles, ?string $winningFileValue): array
    {
        $realValue = $this->resolveRuntimeValue($key);
        $resolved = null !== $realValue;

        if ($resolved) {
            if ([] === $declaredInFiles) {
                $state = 'ambient_only';
            } elseif ($realValue === $winningFileValue) {
                // Compares against the winning file only: an older file that happens to declare
                // the same value the ambient environment carries does not mean it still wins.
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

    /**
     * Mirrors `Dotenv::loadEnv()`: it reads the base file before `APP_ENV`, so a project setting
     * it only in `.env` resolves the same way here. Ambient still wins when set.
     */
    private function resolveAppEnv(): string
    {
        $value = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV');

        if (\is_string($value) && '' !== $value) {
            return $value;
        }

        $fileValue = $this->declaredInBaseFile('APP_ENV');
        if (null !== $fileValue && '' !== $fileValue) {
            return $fileValue;
        }

        return 'dev';
    }

    /**
     * Reads one key from the base file only; the full candidate list needs `$appEnv` to build,
     * which isn't resolved yet at this point.
     */
    private function declaredInBaseFile(string $key): ?string
    {
        $path = $this->projectDir.'/'.$this->baseFile();

        if (!is_file($path)) {
            return null;
        }

        try {
            $parsed = (new Dotenv())->parse((string) file_get_contents($path), $path);
        } catch (FormatException) {
            return null;
        }

        return $parsed[$key] ?? null;
    }

    private function baseFile(): string
    {
        if (!is_file($this->projectDir.'/.env') && is_file($this->projectDir.'/.env.dist')) {
            return '.env.dist';
        }

        return '.env';
    }

    /**
     * Mirrors `Dotenv::loadEnv()`'s file discovery: `.env` (or `.env.dist` as a fallback), then
     * `.env.local` (skipped in a test environment, matching Symfony's own rule), then
     * `.env.$APP_ENV`, then `.env.$APP_ENV.local`.
     *
     * `.env.local` is still listed when skipped, so it shows as present without its declarations
     * being considered.
     *
     * @return list<FileCandidate>
     */
    private function candidateFiles(string $appEnv): array
    {
        return [
            ['file' => $this->baseFile(), 'considered' => true],
            ['file' => '.env.local', 'considered' => !\in_array($appEnv, self::TEST_ENVS, true)],
            ['file' => '.env.'.$appEnv, 'considered' => true],
            ['file' => '.env.'.$appEnv.'.local', 'considered' => true],
        ];
    }

    /**
     * A measured fact about Mate's own CLI process ($_SERVER, then $_ENV, then getenv()), not the
     * app's: the same process-boundary caveat `server-info` documents, so a file-only variable can
     * show as unresolved here even though the real app resolves it once its bootstrap loads it.
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
     * First and last character only, or a fixed short-value mask when that would be most of the
     * value. `length` already reports the real length separately, so the preview doesn't need to.
     * Uses character, not byte, indexing: a byte-sliced multi-byte character is invalid UTF-8.
     */
    private function maskPreview(string $value): string
    {
        $length = mb_strlen($value);

        if ($length <= 2) {
            return '**';
        }

        return mb_substr($value, 0, 1).'***'.mb_substr($value, -1, 1);
    }

    /**
     * Based on the value's content only, never its key name; a hit reveals nothing beyond what
     * {@see maskPreview()} already shows.
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

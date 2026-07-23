<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Bridge\Knowledge\Exception\SyncFailedException;
use Symfony\AI\Mate\Bridge\Knowledge\Model\TocNode;

/**
 * Walks RST `.. toctree::` directives starting from an entry-point file
 * and produces a parent/child {@see TocNode} tree.
 *
 * Toctree parsing rules mirror Symfony\AI\Store\Document\Loader\RstToctreeLoader
 * (handles "Title <entry>" syntax, absolute paths, glob patterns, .rst files
 * vs. directories with index.rst).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TocBuilder
{
    private const RST_ADORNMENT_CHARS = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';

    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Builds the TOC tree starting from the given entry point.
     *
     * @param string $entryPoint Absolute path to the root .rst file (typically index.rst)
     * @param string $rootDir    Absolute path to the documentation root directory
     */
    public function build(string $entryPoint, string $rootDir): TocNode
    {
        if (!file_exists($entryPoint)) {
            throw new SyncFailedException(\sprintf('Entry point "%s" does not exist.', $entryPoint));
        }

        $visited = [];

        return $this->buildNode($entryPoint, $rootDir, $visited, true);
    }

    /**
     * @param array<string, true> $visited
     */
    private function buildNode(string $absolutePath, string $rootDir, array &$visited, bool $isRoot = false): TocNode
    {
        $visited[$absolutePath] = true;

        $content = file_get_contents($absolutePath);
        if (false === $content) {
            throw new SyncFailedException(\sprintf('Could not read "%s".', $absolutePath));
        }

        $title = $this->extractFirstTitle($content) ?? $this->fallbackTitle($absolutePath, $rootDir);
        $relativePath = $isRoot ? '' : $this->toRelativePath($absolutePath, $rootDir);

        $node = new TocNode($relativePath, $title, true);

        foreach ($this->parseToctreeEntries($content, \dirname($absolutePath), $rootDir) as $childPath) {
            if (isset($visited[$childPath])) {
                continue;
            }

            $node->addChild($this->buildNode($childPath, $rootDir, $visited));
        }

        return $node;
    }

    private function toRelativePath(string $absolutePath, string $rootDir): string
    {
        $rootDir = rtrim($rootDir, '/');

        if (!str_starts_with($absolutePath, $rootDir.'/')) {
            return $absolutePath;
        }

        $relative = substr($absolutePath, \strlen($rootDir) + 1);

        if (str_ends_with($relative, '/index.rst')) {
            return substr($relative, 0, -\strlen('/index.rst'));
        }

        if (str_ends_with($relative, '.rst')) {
            return substr($relative, 0, -\strlen('.rst'));
        }

        return $relative;
    }

    private function fallbackTitle(string $absolutePath, string $rootDir): string
    {
        $relative = $this->toRelativePath($absolutePath, $rootDir);
        $base = basename($relative);

        return ucwords(str_replace(['-', '_', '/'], ' ', $base));
    }

    private function extractFirstTitle(string $content): ?string
    {
        $lines = explode(\PHP_EOL, $content);
        $count = \count($lines);

        for ($i = 0; $i < $count - 1; ++$i) {
            $line = trim($lines[$i]);
            $next = trim($lines[$i + 1]);

            if ('' === $line || '' === $next) {
                continue;
            }

            if (!$this->isAdornmentLine($next)) {
                continue;
            }

            if (mb_strlen($next) >= mb_strlen($line)) {
                return $line;
            }
        }

        return null;
    }

    private function isAdornmentLine(string $line): bool
    {
        if ('' === $line) {
            return false;
        }

        $char = $line[0];

        if (!str_contains(self::RST_ADORNMENT_CHARS, $char)) {
            return false;
        }

        return str_repeat($char, \strlen($line)) === $line;
    }

    /**
     * @return list<string>
     */
    private function parseToctreeEntries(string $content, string $baseDir, string $rootDir): array
    {
        $lines = explode(\PHP_EOL, $content);
        $count = \count($lines);
        $entries = [];
        $i = 0;

        while ($i < $count) {
            if (1 !== preg_match('/^(\s*)\.\. toctree::/i', $lines[$i], $match)) {
                ++$i;
                continue;
            }

            $directiveIndent = \strlen($match[1]);
            ++$i;

            while ($i < $count) {
                $line = $lines[$i];

                if ('' === trim($line)) {
                    ++$i;
                    continue;
                }

                $lineIndent = \strlen($line) - \strlen(ltrim($line));

                if ($lineIndent <= $directiveIndent) {
                    break;
                }

                $trimmed = trim($line);

                if (str_starts_with($trimmed, ':')) {
                    ++$i;
                    continue;
                }

                $entryPath = $trimmed;
                if (1 === preg_match('/^.*<(.+?)>$/', $trimmed, $entryMatch)) {
                    $entryPath = trim($entryMatch[1]);
                }

                if (str_starts_with($entryPath, '/')) {
                    $dir = $rootDir;
                    $entryPath = ltrim($entryPath, '/');
                } else {
                    $dir = $baseDir;
                }

                if (str_ends_with($entryPath, '.rst')) {
                    $pattern = $dir.'/'.$entryPath;
                } elseif (str_ends_with($entryPath, '/')) {
                    $pattern = $dir.'/'.$entryPath.'index.rst';
                } else {
                    $pattern = $dir.'/'.$entryPath.'.rst';
                }

                if (str_contains($entryPath, '*') || str_contains($entryPath, '?')) {
                    $globbed = glob($pattern);
                    if (false !== $globbed) {
                        sort($globbed);
                        foreach ($globbed as $globPath) {
                            if (!\in_array($globPath, $entries, true)) {
                                $entries[] = $globPath;
                            }
                        }
                    }
                    ++$i;
                    continue;
                }

                if (!file_exists($pattern)) {
                    $this->logger->warning('Skipping toctree entry — file not found.', [
                        'entry' => $entryPath,
                        'path' => $pattern,
                    ]);
                    ++$i;
                    continue;
                }

                if (!\in_array($pattern, $entries, true)) {
                    $entries[] = $pattern;
                }

                ++$i;
            }
        }

        return $entries;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Loader;

use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;

/**
 * Loads RST documentation files by following toctree directives and splitting at section boundaries.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RstToctreeLoader implements LoaderInterface
{
    public function __construct(
        private RstLoader $rstLoader = new RstLoader(),
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return iterable<EmbeddableDocumentInterface>
     */
    public function load(?string $source = null, array $options = []): iterable
    {
        if (null === $source) {
            throw new InvalidArgumentException('RstToctreeLoader requires a file path as source, null given.');
        }

        yield from $this->processFile($source, 0, \dirname($source));
    }

    /**
     * @return iterable<EmbeddableDocumentInterface>
     */
    private function processFile(string $path, int $depth, string $rootDir): iterable
    {
        if (!file_exists($path)) {
            throw new RuntimeException(\sprintf('File "%s" does not exist.', $path));
        }

        $content = file_get_contents($path);

        if (false === $content) {
            throw new RuntimeException(\sprintf('Could not read file "%s".', $path));
        }

        yield from $this->rstLoader->loadContent($content, $path, ['depth' => $depth]);

        foreach ($this->parseToctreeEntries($content, \dirname($path), $rootDir) as $entryPath) {
            yield from $this->processFile($entryPath, $depth + 1, $rootDir);
        }
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
            if (preg_match('/^(\s*)\.\. toctree::/i', $lines[$i], $match)) {
                $directiveIndent = \strlen($match[1]);
                ++$i;

                // Read directive body (lines indented more than the directive)
                while ($i < $count) {
                    $line = $lines[$i];

                    if ('' === trim($line)) {
                        ++$i;
                        continue;
                    }

                    $lineIndent = \strlen($line) - \strlen(ltrim($line));

                    if ($lineIndent <= $directiveIndent) {
                        // End of directive body
                        break;
                    }

                    $trimmed = trim($line);

                    // Skip directive options (e.g., :maxdepth:, :caption:)
                    if (!str_starts_with($trimmed, ':')) {
                        // Handle "Title <entry>" format
                        if (preg_match('/^.*<(.+?)>$/', $trimmed, $entryMatch)) {
                            $entryPath = trim($entryMatch[1]);
                        } else {
                            $entryPath = $trimmed;
                        }

                        // Absolute paths (starting with /) resolve from doc root
                        if (str_starts_with($entryPath, '/')) {
                            $dir = $rootDir;
                            $entryPath = ltrim($entryPath, '/');
                        } else {
                            $dir = $baseDir;
                        }

                        if (str_ends_with($entryPath, '.rst')) {
                            $pattern = $dir.'/'.$entryPath;
                        } else {
                            $pattern = $dir.'/'.$entryPath.'.rst';
                        }

                        // Expand glob patterns (e.g. "setup/*")
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
                        } else {
                            if (!file_exists($pattern)) {
                                throw new RuntimeException(\sprintf('Toctree entry "%s" resolved to "%s" which does not exist.', $entryPath, $pattern));
                            }

                            if (!\in_array($pattern, $entries, true)) {
                                $entries[] = $pattern;
                            }
                        }
                    }

                    ++$i;
                }

                continue;
            }

            ++$i;
        }

        return $entries;
    }
}

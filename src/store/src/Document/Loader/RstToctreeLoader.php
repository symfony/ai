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
    private RstLoader $rstLoader;

    public function __construct(?RstLoader $rstLoader = null)
    {
        $this->rstLoader = $rstLoader ?? new RstLoader();
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

        yield from $this->processFile($source, 0);
    }

    /**
     * @return iterable<EmbeddableDocumentInterface>
     */
    private function processFile(string $path, int $depth): iterable
    {
        if (!file_exists($path)) {
            throw new RuntimeException(\sprintf('File "%s" does not exist.', $path));
        }

        $content = file_get_contents($path);

        if (false === $content) {
            throw new RuntimeException(\sprintf('Could not read file "%s".', $path));
        }

        yield from $this->rstLoader->load($path, ['depth' => $depth]);

        foreach ($this->parseToctreeEntries($content, \dirname($path)) as $entryPath) {
            yield from $this->processFile($entryPath, $depth + 1);
        }
    }

    /**
     * @return list<string>
     */
    private function parseToctreeEntries(string $content, string $baseDir): array
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

                        $resolvedPath = $baseDir.'/'.$entryPath.'.rst';
                        if (file_exists($resolvedPath)) {
                            $entries[] = $resolvedPath;
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

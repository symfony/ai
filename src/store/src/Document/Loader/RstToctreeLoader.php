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
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * Loads RST documentation files by following toctree directives and splitting at section boundaries.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RstToctreeLoader implements LoaderInterface
{
    private const RST_ADORNMENT_CHARS = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';
    private const MAX_SECTION_LENGTH = 15000;
    private const OVERFLOW_CHUNK_SIZE = 15000;
    private const OVERFLOW_OVERLAP = 200;

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

        if (!file_exists($source)) {
            throw new RuntimeException(\sprintf('File "%s" does not exist.', $source));
        }

        foreach ($this->processFile($source, 0) as $document) {
            yield $document;
        }
    }

    /**
     * @return iterable<EmbeddableDocumentInterface>
     */
    private function processFile(string $path, int $depth): iterable
    {
        $content = file_get_contents($path);

        if (false === $content) {
            throw new RuntimeException(\sprintf('Could not read file "%s".', $path));
        }

        $dir = \dirname($path);

        foreach ($this->splitIntoSections($content, $path, $depth) as $document) {
            yield $document;
        }

        foreach ($this->parseToctreeEntries($content, $dir) as $entryPath) {
            foreach ($this->processFile($entryPath, $depth + 1) as $document) {
                yield $document;
            }
        }
    }

    /**
     * @return iterable<EmbeddableDocumentInterface>
     */
    private function splitIntoSections(string $content, string $source, int $depth): iterable
    {
        $lines = explode("\n", $content);
        $count = \count($lines);

        $currentTitle = '';
        $sectionStartIndex = 0;
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];
            $nextLine = $i + 1 < $count ? $lines[$i + 1] : '';

            if ($this->isHeading($line, $nextLine)) {
                if ($i > $sectionStartIndex) {
                    $sectionLines = \array_slice($lines, $sectionStartIndex, $i - $sectionStartIndex);
                    $sectionText = implode("\n", $sectionLines);
                    if ('' !== trim($sectionText)) {
                        foreach ($this->yieldSection($sectionText, $currentTitle, $source, $depth, null) as $document) {
                            yield $document;
                        }
                    }
                }

                $currentTitle = trim($line);
                $sectionStartIndex = $i;
                $i += 2; // Skip the heading line and adornment line

                continue;
            }

            ++$i;
        }

        if ($sectionStartIndex < $count) {
            $sectionLines = \array_slice($lines, $sectionStartIndex);
            $sectionText = implode("\n", $sectionLines);
            if ('' !== trim($sectionText)) {
                foreach ($this->yieldSection($sectionText, $currentTitle, $source, $depth, null) as $document) {
                    yield $document;
                }
            }
        }
    }

    /**
     * @return iterable<EmbeddableDocumentInterface>
     */
    private function yieldSection(string $text, string $title, string $source, int $depth, int|string|null $parentId): iterable
    {
        if (mb_strlen($text) <= self::MAX_SECTION_LENGTH) {
            $metadata = new Metadata([
                Metadata::KEY_SOURCE => $source,
                Metadata::KEY_TEXT => $text,
                Metadata::KEY_SECTION_TITLE => $title,
                Metadata::KEY_DEPTH => $depth,
            ]);

            if (null !== $parentId) {
                $metadata->setParentId($parentId);
            }

            yield new TextDocument(Uuid::v4()->toRfc4122(), $text, $metadata);

            return;
        }

        // Section overflow: split using character-based chunking
        $sectionId = Uuid::v4()->toRfc4122();
        $chunkSize = self::OVERFLOW_CHUNK_SIZE;
        $overlap = self::OVERFLOW_OVERLAP;
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $chunkSize, $length);
            $chunkText = mb_substr($text, $start, $end - $start);

            $metadata = new Metadata([
                Metadata::KEY_SOURCE => $source,
                Metadata::KEY_TEXT => $chunkText,
                Metadata::KEY_SECTION_TITLE => $title,
                Metadata::KEY_DEPTH => $depth,
                Metadata::KEY_PARENT_ID => $sectionId,
            ]);

            yield new TextDocument(Uuid::v4()->toRfc4122(), $chunkText, $metadata);

            $start += ($chunkSize - $overlap);
        }
    }

    private function isHeading(string $line, string $nextLine): bool
    {
        $trimmedLine = trim($line);
        $trimmedNext = trim($nextLine);

        if ('' === $trimmedLine || '' === $trimmedNext) {
            return false;
        }

        if (!$this->isAdornmentLine($trimmedNext)) {
            return false;
        }

        return mb_strlen($trimmedNext) >= mb_strlen($trimmedLine);
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
    private function parseToctreeEntries(string $content, string $baseDir): array
    {
        $lines = explode("\n", $content);
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
            } else {
                ++$i;
            }
        }

        return $entries;
    }
}

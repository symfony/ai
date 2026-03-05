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
 * Loads a single RST file and splits it into sections at heading boundaries.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RstLoader implements LoaderInterface
{
    private const RST_ADORNMENT_CHARS = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';
    private const MAX_SECTION_LENGTH = 15000;
    private const OVERFLOW_OVERLAP = 200;

    /**
     * @param array<string, mixed> $options accepts 'depth' (int, default 0)
     *
     * @return iterable<EmbeddableDocumentInterface>
     */
    public function load(?string $source = null, array $options = []): iterable
    {
        if (null === $source) {
            throw new InvalidArgumentException('RstLoader requires a file path as source, null given.');
        }

        if (!file_exists($source)) {
            throw new RuntimeException(\sprintf('File "%s" does not exist.', $source));
        }

        $content = file_get_contents($source);

        if (false === $content) {
            throw new RuntimeException(\sprintf('Could not read file "%s".', $source));
        }

        $depth = (int) ($options['depth'] ?? 0);

        yield from $this->splitIntoSections($content, $source, $depth);
    }

    /**
     * @return iterable<EmbeddableDocumentInterface>
     */
    private function splitIntoSections(string $content, string $source, int $depth): iterable
    {
        $lines = explode(\PHP_EOL, $content);
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
                    $sectionText = implode(\PHP_EOL, $sectionLines);
                    if ('' !== trim($sectionText)) {
                        yield from $this->yieldSection($sectionText, $currentTitle, $source, $depth);
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
            $sectionText = implode(\PHP_EOL, $sectionLines);
            if ('' !== trim($sectionText)) {
                yield from $this->yieldSection($sectionText, $currentTitle, $source, $depth);
            }
        }
    }

    /**
     * @return iterable<EmbeddableDocumentInterface>
     */
    private function yieldSection(string $text, string $title, string $source, int $depth): iterable
    {
        if (mb_strlen($text) <= self::MAX_SECTION_LENGTH) {
            $metadata = new Metadata([
                Metadata::KEY_SOURCE => $source,
                Metadata::KEY_TEXT => $text,
                Metadata::KEY_TITLE => $title,
                Metadata::KEY_DEPTH => $depth,
            ]);

            yield new TextDocument(Uuid::v4()->toRfc4122(), $text, $metadata);

            return;
        }

        // Section overflow: split using character-based chunking
        $sectionId = Uuid::v4()->toRfc4122();
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = min($start + self::MAX_SECTION_LENGTH, $length);
            $chunkText = mb_substr($text, $start, $end - $start);

            $metadata = new Metadata([
                Metadata::KEY_SOURCE => $source,
                Metadata::KEY_TEXT => $chunkText,
                Metadata::KEY_TITLE => $title,
                Metadata::KEY_DEPTH => $depth,
                Metadata::KEY_PARENT_ID => $sectionId,
            ]);

            yield new TextDocument(Uuid::v4()->toRfc4122(), $chunkText, $metadata);

            $start += (self::MAX_SECTION_LENGTH - self::OVERFLOW_OVERLAP);
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
}

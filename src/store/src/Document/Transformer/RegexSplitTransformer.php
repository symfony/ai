<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Transformer;

use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * Splits a TextDocument into sections, starting a new section at each match of a regex pattern.
 *
 * @author MD Ali Kadar <ali.kodezen@gmail.com>
 */
final class RegexSplitTransformer implements TransformerInterface
{
    public const OPTION_PATTERN = 'pattern';
    public const OPTION_KEEP_DELIMITER = 'keep_delimiter';

    private const MARKDOWN_HEADING_PATTERN = '/^(#{1,6})[ \t]+(.+?)(?:[ \t]+#+)?[ \t]*$/';

    public function __construct(
        private readonly string $pattern,
        private readonly bool $keepDelimiter = true,
    ) {
        self::validatePattern($pattern);
    }

    /**
     * @param array{pattern?: string, keep_delimiter?: bool} $options
     */
    public function transform(iterable $documents, array $options = []): iterable
    {
        $pattern = $this->pattern;
        if (isset($options[self::OPTION_PATTERN])) {
            $pattern = $options[self::OPTION_PATTERN];
            self::validatePattern($pattern);
        }

        $keepDelimiter = $options[self::OPTION_KEEP_DELIMITER] ?? $this->keepDelimiter;

        foreach ($documents as $document) {
            $content = $document->getContent();

            if (false === preg_match_all($pattern, $content, $matches, \PREG_OFFSET_CAPTURE)) {
                throw new RuntimeException(\sprintf('Splitting the document "%s" with the pattern "%s" failed: "%s".', $document->getId(), $pattern, preg_last_error_msg()));
            }

            $sections = [] === $matches[0] ? [] : iterator_to_array($this->splitIntoSections($content, $matches[0], $keepDelimiter), false);

            if ([] === $sections) {
                $metadata = new Metadata([
                    ...$document->getMetadata(),
                    Metadata::KEY_TEXT => $document->getContent(),
                ]);

                yield new TextDocument($document->getId(), $document->getContent(), $metadata);

                continue;
            }

            foreach ($sections as [$title, $depth, $text]) {
                $metadata = [
                    ...$document->getMetadata(),
                    Metadata::KEY_PARENT_ID => $document->getId(),
                    Metadata::KEY_TEXT => $text,
                ];

                if (null !== $title) {
                    $metadata[Metadata::KEY_TITLE] = $title;
                    $metadata[Metadata::KEY_DEPTH] = $depth;
                }

                yield new TextDocument(Uuid::v4(), $text, new Metadata($metadata));
            }
        }
    }

    /**
     * @param non-empty-list<array{0: string, 1: int}> $delimiters
     *
     * @return iterable<array{0: string|null, 1: int|null, 2: string}>
     */
    private function splitIntoSections(string $content, array $delimiters, bool $keepDelimiter): iterable
    {
        $title = null;
        $depth = null;
        $text = substr($content, 0, $delimiters[0][1]);

        foreach ($delimiters as $i => [$delimiter, $offset]) {
            if ('' !== trim($text)) {
                yield [$title, $depth, trim($text)];
            }

            $heading = trim($delimiter);
            [$title, $depth] = self::extractHeadingMetadata($heading);
            $bodyStart = $offset + \strlen($delimiter);
            $bodyEnd = $delimiters[$i + 1][1] ?? \strlen($content);
            $body = substr($content, $bodyStart, $bodyEnd - $bodyStart);
            $text = $keepDelimiter ? $heading.$body : $body;
        }

        if ('' !== trim($text)) {
            yield [$title, $depth, trim($text)];
        }
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private static function extractHeadingMetadata(string $heading): array
    {
        if (!preg_match(self::MARKDOWN_HEADING_PATTERN, $heading, $matches)) {
            return [null, null];
        }

        return [trim($matches[2]), \strlen($matches[1])];
    }

    private static function validatePattern(string $pattern): void
    {
        if (false === @preg_match($pattern, '')) {
            throw new InvalidArgumentException(\sprintf('The pattern "%s" is not a valid regular expression.', $pattern));
        }
    }
}

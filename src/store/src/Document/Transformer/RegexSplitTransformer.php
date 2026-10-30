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
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Uid\Uuid;

/**
 * Splits a TextDocument's content on a regex pattern, meant to run before TextSplitTransformer
 * so each matched section (e.g. a Markdown heading) can be chunked independently.
 *
 * The pattern must contain exactly one capturing group around the delimiter, e.g.
 * '/^(#{1,6}\s+.+)$/m' to split on Markdown headings.
 *
 * @author MD Ali Kadar <ali.kodezen@gmail.com>
 */
final class RegexSplitTransformer implements TransformerInterface
{
    public const OPTION_PATTERN = 'pattern';
    public const OPTION_KEEP_DELIMITER = 'keep_delimiter';

    public function __construct(
        private readonly string $pattern,
        private readonly bool $keepDelimiter = true,
    ) {
    }

    /**
     * @param array{pattern?: string, keep_delimiter?: bool} $options
     */
    public function transform(iterable $documents, array $options = []): iterable
    {
        if (!class_exists(UnicodeString::class)) {
            throw new RuntimeException('For using the RegexSplitTransformer, the Symfony String component is required. Try running "composer require symfony/string".');
        }

        $pattern = $options[self::OPTION_PATTERN] ?? $this->pattern;
        $keepDelimiter = $options[self::OPTION_KEEP_DELIMITER] ?? $this->keepDelimiter;

        foreach ($documents as $document) {
            $parts = (new UnicodeString($document->getContent()))->split($pattern, flags: \PREG_SPLIT_DELIM_CAPTURE);

            if (1 === \count($parts)) {
                yield $document;

                continue;
            }

            foreach ($this->splitIntoSections($parts, $keepDelimiter) as [$title, $depth, $text]) {
                $metadata = [
                    ...$document->getMetadata(),
                    Metadata::KEY_PARENT_ID => $document->getId(),
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
     * @param list<UnicodeString> $parts
     *
     * @return iterable<array{0: string|null, 1: int, 2: string}>
     */
    private function splitIntoSections(array $parts, bool $keepDelimiter): iterable
    {
        $title = null;
        $depth = 0;
        $text = (string) $parts[0];

        for ($i = 1; $i < \count($parts); $i += 2) {
            if ('' !== trim($text)) {
                yield [$title, $depth, trim($text)];
            }

            $heading = trim((string) $parts[$i]);
            $title = $heading;
            $depth = strspn($heading, '#');
            $body = (string) ($parts[$i + 1] ?? '');
            $text = $keepDelimiter ? $heading.$body : $body;
        }

        if ('' !== trim($text)) {
            yield [$title, $depth, trim($text)];
        }
    }
}

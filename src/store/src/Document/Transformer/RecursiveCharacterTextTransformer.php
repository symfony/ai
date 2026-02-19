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
use Symfony\Component\Uid\Uuid;

class RecursiveCharacterTextTransformer implements TransformerInterface
{
    public const DEFAULT_OPTION_CHUNK_SIZE = 'chunk_size';
    public const DEFAULT_OPTION_SEPARATORS = 'separators';

    /**
     * @param non-empty-list<string> $separators
     */
    public function __construct(
        private readonly array $separators,
        private readonly int $chunkSize,
    ) {}

    public function transform(
        iterable $documents,
        array $options = [],
    ): iterable {
        $chunkSize = $options[self::DEFAULT_OPTION_CHUNK_SIZE] ?? $this->chunkSize;
        $separators = $options[self::DEFAULT_OPTION_SEPARATORS] ?? $this->separators;
        foreach ($documents as $document) {
            if (!$document instanceof TextDocument) {
                yield $document;
                continue;
            }
            $documentContent = $document->getContent();
            $splits = $this->splitText($documentContent, $separators, $chunkSize);
            foreach ($splits as $chunkText) {
                yield new TextDocument(
                    Uuid::v4(),
                    $chunkText,
                    new Metadata([
                        Metadata::KEY_PARENT_ID => $document->getId(),
                        Metadata::KEY_TEXT => $chunkText,
                        ...$document->getMetadata(),
                    ]),
                );
            }
        }
    }

    /**
     * @param non-empty-list<string> $separators
     * @return iterable<string>
     */
    private static function splitText(string $text, array $separators, int $chunkSize): iterable
    {
        $pieces = '' === $separators[0] ? \mb_str_split($text, $chunkSize) : \explode($separators[0], $text);
        $splits = [];
        foreach ($pieces as $piece) {
            if (\mb_strlen($piece) > $chunkSize) {
                $splits = [...$splits, ...self::splitText($piece, \array_slice($separators, 1) ?: [''], $chunkSize)];
            } else {
                $splits[] = $piece;
            }
        }
        // now we can merge splits if they fit within chunk size
        $currentSplit = '';
        foreach ($splits as $split) {
            if (\mb_strlen($currentSplit . $split) > $chunkSize) {
                yield $currentSplit;
                $currentSplit = $split;
            } else {
                $currentSplit .= $split;
            }
        }
        yield $currentSplit;
    }
}

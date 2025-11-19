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

use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Replaces specified text within document content.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class TextReplaceTransformer implements TransformerInterface
{
    public const OPTION_SEARCH = 'search';
    public const OPTION_REPLACE = 'replace';

    public function __construct(
        private readonly string $search = '',
        private readonly string $replace = '',
    ) {
        self::validate($search, $replace);
    }

    /**
     * @param iterable<TextDocument>                   $documents
     * @param array{search?: string, replace?: string} $options
     */
    public function transform(iterable $documents, array $options = []): iterable
    {
        $search = $options[self::OPTION_SEARCH] ?? $this->search;
        $replace = $options[self::OPTION_REPLACE] ?? $this->replace;

        self::validate($search, $replace);

        foreach ($documents as $document) {
            yield $document->withContent(str_replace($search, $replace, $document->getContent()));
        }
    }

    private static function validate(string $search, string $replace): void
    {
        if ($search === $replace) {
            throw new InvalidArgumentException('Search and replace strings must be different.');
        }
    }
}

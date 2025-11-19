<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Filter;

use Symfony\AI\Store\Document\FilterInterface;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Filters documents based on text content matching a specified string.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class TextContainsFilter implements FilterInterface
{
    public const OPTION_NEEDLE = 'needle';
    public const OPTION_CASE_SENSITIVE = 'case_sensitive';

    /**
     * @param non-empty-string $needle
     */
    public function __construct(
        private string $needle,
        private bool $caseSensitive = false,
    ) {
        if ('' === trim($needle)) {
            throw new InvalidArgumentException('Needle cannot be an empty string.');
        }
    }

    /**
     * @param iterable<TextDocument>                                  $documents
     * @param array{needle?: non-empty-string, case_sensitive?: bool} $options
     *
     * @return iterable<TextDocument>
     */
    public function filter(iterable $documents, array $options = []): iterable
    {
        $needle = $options[self::OPTION_NEEDLE] ?? $this->needle;
        $caseSensitive = $options[self::OPTION_CASE_SENSITIVE] ?? $this->caseSensitive;

        foreach ($documents as $document) {
            $content = $document->getContent();

            if ($caseSensitive) {
                $contains = str_contains($content, $needle);
            } else {
                $contains = str_contains(strtolower($content), strtolower($needle));
            }

            if (!$contains) {
                yield $document;
            }
        }
    }
}

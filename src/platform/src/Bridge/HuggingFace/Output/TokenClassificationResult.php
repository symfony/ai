<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Output;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TokenClassificationResult
{
    /**
     * @param Token[] $tokens
     */
    public function __construct(
        public array $tokens,
    ) {
    }

    /**
     * @param array<array{entity_group: string, score: float, word: string, start: int, end: int}> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(array_map(
            fn (array $item) => new Token(
                $item['entity_group'],
                $item['score'],
                $item['word'],
                $item['start'],
                $item['end'],
            ),
            $data,
        ));
    }
}

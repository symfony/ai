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
final class FillMaskResult
{
    /**
     * @param MaskFill[] $fills
     */
    public function __construct(
        public array $fills,
    ) {
    }

    /**
     * @param array<array{token: int, token_str: string, sequence: string, score: float}> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(array_map(
            fn (array $item) => new MaskFill(
                $item['token'],
                $item['token_str'],
                $item['sequence'],
                $item['score'],
            ),
            $data,
        ));
    }
}

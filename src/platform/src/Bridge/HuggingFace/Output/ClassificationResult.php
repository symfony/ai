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
final class ClassificationResult
{
    /**
     * @param Classification[] $classifications
     */
    public function __construct(
        public array $classifications,
    ) {
    }

    /**
     * @param array<array{label: string, score: float}> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(fn (array $item) => new Classification($item['label'], $item['score']), $data)
        );
    }
}

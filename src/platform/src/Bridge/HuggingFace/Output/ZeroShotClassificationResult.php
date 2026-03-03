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
final class ZeroShotClassificationResult
{
    /**
     * @param array<string> $labels
     * @param array<float>  $scores
     */
    public function __construct(
        public array $labels,
        public array $scores,
        public ?string $sequence = null,
    ) {
    }

    /**
     * @param array{labels: array<string>, scores: array<float>, sequence?: string}|list<array{label: string, score: float}> $data
     */
    public static function fromArray(array $data): self
    {
        // New HF serverless format: [{label: "refund", score: 0.97}, ...]
        if (isset($data[0]['label'])) {
            return new self(
                array_map(static fn (array $item): string => $item['label'], $data),
                array_map(static fn (array $item): float => $item['score'], $data),
            );
        }

        return new self(
            $data['labels'],
            $data['scores'],
            $data['sequence'] ?? null,
        );
    }
}

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
        private readonly array $labels,
        private readonly array $scores,
        private readonly ?string $sequence = null,
    ) {
    }

    /**
     * @return array<string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @return array<float>
     */
    public function getScores(): array
    {
        return $this->scores;
    }

    public function getSequence(): ?string
    {
        return $this->sequence;
    }

    /**
     * @param array{labels: array<string>, scores: array<float>, sequence?: string}|list<array{label: string, score: float}> $data
     */
    public static function fromArray(array $data): self
    {
        // Serverless format: [{label: "refund", score: 0.97}, ...]
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

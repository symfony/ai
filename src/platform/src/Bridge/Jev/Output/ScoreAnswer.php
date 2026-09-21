<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev\Output;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Probability-weighted score across ordered rubric levels.
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ScoreAnswer
{
    /**
     * @param array<string, string> $legend
     * @param array<string, float>  $probabilities
     */
    public function __construct(
        private readonly float $score,
        private readonly array $legend,
        private readonly array $probabilities,
        private readonly ?float $confidence = null,
    ) {
    }

    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * @return array<string, string>
     */
    public function getLegend(): array
    {
        return $this->legend;
    }

    /**
     * @return array<string, float>
     */
    public function getProbabilities(): array
    {
        return $this->probabilities;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['score']) || !is_numeric($data['score'])) {
            throw new RuntimeException('Score answer is missing a numeric "score" value.');
        }

        if (!isset($data['legend']) || !\is_array($data['legend'])) {
            throw new RuntimeException('Score answer is missing a "legend" object.');
        }

        $legend = [];
        foreach ($data['legend'] as $key => $label) {
            $legend[(string) $key] = (string) $label;
        }

        if (!isset($data['probabilities']) || !\is_array($data['probabilities'])) {
            throw new RuntimeException('Score answer is missing a "probabilities" object.');
        }

        $probabilities = [];
        foreach ($data['probabilities'] as $key => $probability) {
            $probabilities[(string) $key] = (float) $probability;
        }

        return new self(
            (float) $data['score'],
            $legend,
            $probabilities,
            isset($data['confidence']) && is_numeric($data['confidence']) ? (float) $data['confidence'] : null,
        );
    }

    /**
     * @return array{type: 'score', score: float, legend: array<string, string>, probabilities: array<string, float>, confidence?: float}
     */
    public function toArray(): array
    {
        $data = [
            'type' => 'score',
            'score' => $this->score,
            'legend' => $this->legend,
            'probabilities' => $this->probabilities,
        ];

        if (null !== $this->confidence) {
            $data['confidence'] = $this->confidence;
        }

        return $data;
    }
}

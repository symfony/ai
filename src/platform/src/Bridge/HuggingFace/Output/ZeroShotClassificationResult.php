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
}

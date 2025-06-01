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
     * @param array{labels: array<string>, scores: array<float>, sequence?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['labels'],
            $data['scores'],
            $data['sequence'] ?? null,
        );
    }
}

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
final class ImageSegmentationResult
{
    /**
     * @param ImageSegment[] $segments
     */
    public function __construct(
        private readonly array $segments,
    ) {
    }

    /**
     * @return ImageSegment[]
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * @param array<array{label: string, score: float, mask: string}> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(static fn (array $item) => new ImageSegment($item['label'], $item['score'], $item['mask']), $data)
        );
    }
}

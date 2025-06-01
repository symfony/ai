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
final class ObjectDetectionResult
{
    /**
     * @param DetectedObject[] $objects
     */
    public function __construct(
        public array $objects,
    ) {
    }

    /**
     * @param array<array{label: string, score: float, box: array{xmin: float, ymin: float, xmax: float, ymax: float}}> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(array_map(
            fn (array $item) => new DetectedObject(
                $item['label'],
                $item['score'],
                $item['box']['xmin'],
                $item['box']['ymin'],
                $item['box']['xmax'],
                $item['box']['ymax'],
            ),
            $data,
        ));
    }
}

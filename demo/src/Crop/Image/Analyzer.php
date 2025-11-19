<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Crop\Image;

use Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class Analyzer
{
    public function __construct(
        #[Autowire(service: 'ai.platform.huggingface')]
        private PlatformInterface $platform,
    ) {
    }

    public function getRelevantArea(string $imageData): RelevantArea
    {
        $result = $this->platform->invoke('facebook/detr-resnet-50', Image::fromDataUrl($imageData), [
            'task' => Task::OBJECT_DETECTION,
        ])->asObject();

        \assert($result instanceof ObjectDetectionResult);

        if ([] === $result->objects) {
            throw new \RuntimeException('No objects detected.');
        }

        $init = $result->objects[0];
        $xMin = $init->xmin;
        $yMin = $init->ymin;
        $xMax = $init->xmax;
        $yMax = $init->ymax;

        foreach ($result->objects as $object) {
            if ($object->xmin < $xMin) {
                $xMin = $object->xmin;
            }
            if ($object->ymin < $yMin) {
                $yMin = $object->ymin;
            }
            if ($object->xmax > $xMax) {
                $xMax = $object->xmax;
            }
            if ($object->ymax > $yMax) {
                $yMax = $object->ymax;
            }
        }

        return new RelevantArea((int) $xMin, (int) $yMin, (int) $xMax, (int) $yMax);
    }
}

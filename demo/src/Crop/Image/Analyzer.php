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

        if ([] === $result->getObjects()) {
            throw new \RuntimeException('No objects detected.');
        }

        $init = $result->getObjects()[0];
        $xMin = $init->getXmin();
        $yMin = $init->getYmin();
        $xMax = $init->getXmax();
        $yMax = $init->getYmax();

        foreach ($result->getObjects() as $object) {
            if ($object->getXmin() < $xMin) {
                $xMin = $object->getXmin();
            }
            if ($object->getYmin() < $yMin) {
                $yMin = $object->getYmin();
            }
            if ($object->getXmax() > $xMax) {
                $xMax = $object->getXmax();
            }
            if ($object->getYmax() > $yMax) {
                $yMax = $object->getYmax();
            }
        }

        return new RelevantArea((int) $xMin, (int) $yMin, (int) $xMax, (int) $yMax);
    }
}

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

use Symfony\AI\Platform\Bridge\HuggingFace\Output\DetectedObject;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Detects the relevant area in an image using Hugging Face with an object detection model.
 *
 * Detections below {@see self::CONFIDENCE_THRESHOLD} are dropped before the
 * union is computed; if every detection is below the threshold, the single
 * highest-scored one is used instead.
 */
final readonly class Analyzer
{
    private const float CONFIDENCE_THRESHOLD = 0.5;

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

        $objects = $result->getObjects();
        if ([] === $objects) {
            throw new \RuntimeException('No objects detected.');
        }

        $relevant = $this->pickRelevant(array_values($objects));

        $xMin = $relevant[0]->getXmin();
        $yMin = $relevant[0]->getYmin();
        $xMax = $relevant[0]->getXmax();
        $yMax = $relevant[0]->getYmax();

        foreach ($relevant as $object) {
            $xMin = min($xMin, $object->getXmin());
            $yMin = min($yMin, $object->getYmin());
            $xMax = max($xMax, $object->getXmax());
            $yMax = max($yMax, $object->getYmax());
        }

        return new RelevantArea((int) floor($xMin), (int) floor($yMin), (int) ceil($xMax), (int) ceil($yMax));
    }

    /**
     * @param list<DetectedObject> $objects
     *
     * @return non-empty-list<DetectedObject>
     */
    private function pickRelevant(array $objects): array
    {
        $confident = array_values(array_filter(
            $objects,
            static fn (DetectedObject $object) => $object->getScore() >= self::CONFIDENCE_THRESHOLD,
        ));

        if ([] !== $confident) {
            return $confident;
        }

        $best = $objects[0];
        foreach ($objects as $object) {
            if ($object->getScore() > $best->getScore()) {
                $best = $object;
            }
        }

        return [$best];
    }
}

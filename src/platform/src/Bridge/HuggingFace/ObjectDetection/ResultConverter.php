<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\ObjectDetection;

use Symfony\AI\Platform\Bridge\HuggingFace\ObjectDetection;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\DetectedObject;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Pipeline\ResponseHandlingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    use ResponseHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof ObjectDetection;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ObjectResult
    {
        /** @var array<array{label: string, score: float, box: array{xmin: float, ymin: float, xmax: float, ymax: float}}> $data */
        $data = $this->getResponseData($result);

        return new ObjectResult(new ObjectDetectionResult(array_map(
            static fn (array $item) => new DetectedObject(
                $item['label'],
                $item['score'],
                $item['box']['xmin'],
                $item['box']['ymin'],
                $item['box']['xmax'],
                $item['box']['ymax'],
            ),
            $data,
        )));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}

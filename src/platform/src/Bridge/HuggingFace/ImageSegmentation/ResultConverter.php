<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\ImageSegmentation;

use Symfony\AI\Platform\Bridge\HuggingFace\ImageSegmentation;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ImageSegment;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ImageSegmentationResult;
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
        return $model instanceof ImageSegmentation;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ObjectResult
    {
        /** @var array<array{label: string, score: float, mask: string}> $data */
        $data = $this->getResponseData($result);

        return new ObjectResult(new ImageSegmentationResult(
            array_map(static fn (array $item) => new ImageSegment($item['label'], $item['score'], $item['mask']), $data)
        ));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}

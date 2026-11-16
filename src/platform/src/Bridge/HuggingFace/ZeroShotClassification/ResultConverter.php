<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\ZeroShotClassification;

use Symfony\AI\Platform\Bridge\HuggingFace\Output\ZeroShotClassificationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Pipeline\ResponseHandlingTrait;
use Symfony\AI\Platform\Bridge\HuggingFace\ZeroShotClassification;
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
        return $model instanceof ZeroShotClassification;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ObjectResult
    {
        /** @var array{labels: array<string>, scores: array<float>, sequence?: string}|list<array{label: string, score: float}> $data */
        $data = $this->getResponseData($result);

        // Serverless format: [{label: "refund", score: 0.97}, ...]
        if (isset($data[0]['label'])) {
            return new ObjectResult(new ZeroShotClassificationResult(
                array_map(static fn (array $item): string => $item['label'], $data),
                array_map(static fn (array $item): float => $item['score'], $data),
            ));
        }

        return new ObjectResult(new ZeroShotClassificationResult(
            $data['labels'],
            $data['scores'],
            $data['sequence'] ?? null,
        ));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}

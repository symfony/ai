<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\TokenClassification;

use Symfony\AI\Platform\Bridge\HuggingFace\Output\Token;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\TokenClassificationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Pipeline\ResponseHandlingTrait;
use Symfony\AI\Platform\Bridge\HuggingFace\TokenClassification;
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
        return $model instanceof TokenClassification;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ObjectResult
    {
        /** @var array<array{entity_group: string, score: float, word: string, start: int, end: int}> $data */
        $data = $this->getResponseData($result);

        return new ObjectResult(new TokenClassificationResult(array_map(
            static fn (array $item) => new Token(
                $item['entity_group'],
                $item['score'],
                $item['word'],
                $item['start'],
                $item['end'],
            ),
            $data,
        )));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}

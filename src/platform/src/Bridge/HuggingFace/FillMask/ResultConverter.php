<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\FillMask;

use Symfony\AI\Platform\Bridge\HuggingFace\FillMask;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\FillMaskResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\MaskFill;
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
        return $model instanceof FillMask;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ObjectResult
    {
        /** @var array<array{token: int, token_str: string, sequence: string, score: float}> $data */
        $data = $this->getResponseData($result);

        return new ObjectResult(new FillMaskResult(array_map(
            static fn (array $item) => new MaskFill(
                $item['token'],
                $item['token_str'],
                $item['sequence'],
                $item['score'],
            ),
            $data,
        )));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}

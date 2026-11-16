<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Translation;

use Symfony\AI\Platform\Bridge\HuggingFace\Pipeline\ResponseHandlingTrait;
use Symfony\AI\Platform\Bridge\HuggingFace\Translation;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
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
        return $model instanceof Translation;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): TextResult
    {
        $data = $this->getResponseData($result);

        if (!isset($data[0]['translation_text'])) {
            throw new RuntimeException('Response does not contain translation text.');
        }

        return new TextResult($data[0]['translation_text']);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}

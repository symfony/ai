<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Speech;

use Symfony\AI\Platform\Bridge\OpenRouter\SpeechModel;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechModel;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $response->getStatusCode(), $response->getContent(false)));
        }

        return new BinaryResult($response->getContent());
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}

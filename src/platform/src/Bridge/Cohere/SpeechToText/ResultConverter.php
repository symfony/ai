<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\SpeechToText;

use Symfony\AI\Platform\Bridge\Cohere\SpeechToText;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechToText;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): TextResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['text'])) {
            throw new RuntimeException('Response does not contain transcription text.');
        }

        return new TextResult($data['text']);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}

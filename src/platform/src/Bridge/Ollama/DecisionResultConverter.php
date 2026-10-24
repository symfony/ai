<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Bridge\Decision\AnswersConverter;
use Symfony\AI\Platform\Bridge\Decision\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Converts the "POST /v1/systemone" response of Ollama into {@see \Symfony\AI\Platform\Bridge\Decision\Answer\Answers}.
 *
 * Ollama reports errors as {"error": "message"}; the success payload is shared with other decision providers.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DecisionResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Decision;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            // e.g. 413 when the request exceeds the size limit
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $this->extractErrorMessage($httpResponse) ?? $httpResponse->getContent(false)));
        }

        return new ObjectResult((new AnswersConverter())->convert($result->getData()));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        $error = $data['error'] ?? null;

        return \is_string($error) ? $error : null;
    }
}

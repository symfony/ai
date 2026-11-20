<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Completions;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * This default implementation is based on the OpenAI GPT completion API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
class ResultConverter implements ResultConverterInterface
{
    use CompletionsConversionTrait;
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof CompletionsModel;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if ($response instanceof ResponseInterface) {
            if (400 === $response->getStatusCode()) {
                $this->throwOnContextLengthExceeded($response);
            }

            if (403 === $response->getStatusCode()) {
                throw new AuthenticationException($this->buildHttpErrorMessage($response));
            }

            $this->throwOnHttpError($response);

            if ($response->getStatusCode() >= 400) {
                throw new RuntimeException($this->buildHttpErrorMessage($response));
            }
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (\is_string($data['error'] ?? null)) {
            throw new RuntimeException($data['error']);
        }

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error "%s"-%s (%s): "%s".', $data['error']['code'] ?? '-', $data['error']['type'] ?? '-', $data['error']['param'] ?? '-', $data['error']['message'] ?? '-'));
        }

        if (!isset($data['choices'])) {
            throw new RuntimeException('Response does not contain choices.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    private function throwOnContextLengthExceeded(ResponseInterface $response): void
    {
        try {
            $error = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return;
        }

        $errorCode = (\is_array($error['error'] ?? null) ? ($error['error']['code'] ?? null) : null) ?? $error['code'] ?? null;
        $errorMessage = $this->extractErrorMessage($response) ?? '';

        if ('context_length_exceeded' === $errorCode || 1 === preg_match('/context[_ ]length[_ ]exceeded/i', $errorMessage)) {
            throw new ExceedContextSizeException('' !== $errorMessage ? $errorMessage : $this->buildHttpErrorMessage($response));
        }
    }
}

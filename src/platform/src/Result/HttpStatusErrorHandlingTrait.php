<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provides shared HTTP error handling for bridge result converters.
 *
 * Translates the common 4xx/429 and 5xx responses returned by AI providers
 * into dedicated platform exceptions so consumers can react to auth failures,
 * bad requests, missing models, rate limits and transient server errors
 * without parsing error bodies themselves.
 *
 * Error bodies are parsed leniently: both the nested `error.message` shape
 * (OpenAI, Gemini, Perplexity) and the flat top-level `message` shape
 * (Mistral, Cerebras, Cohere) are supported.
 *
 * On 429, the retry time comes from the `Retry-After` header, or else from the
 * `google.rpc.RetryInfo` detail of the error body (`error.details[].retryDelay`),
 * which Gemini sends instead of a header. Providers that expose retry hints
 * through other mechanisms (e.g. Cerebras via `x-ratelimit-reset-*`) are not
 * parsed here and will yield a null `getRetryAfter()`.
 *
 * @author Pascal CESCON <pascal.cescon@gmail.com>
 */
trait HttpStatusErrorHandlingTrait
{
    private function throwOnHttpError(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();

        if (401 === $status) {
            throw new AuthenticationException($this->extractErrorMessage($response) ?? 'Unauthorized');
        }

        if (400 === $status) {
            throw new BadRequestException($this->extractErrorMessage($response) ?? 'Bad Request');
        }

        if (404 === $status) {
            throw new ModelNotFoundException($this->extractErrorMessage($response) ?? 'Not Found');
        }

        if (429 === $status) {
            throw new RateLimitExceededException($this->extractRetryAfter($response), $this->extractErrorMessage($response));
        }

        if ($status >= 500) {
            throw new ServerException($status, $this->extractErrorMessage($response));
        }
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        return $data['error']['message'] ?? $data['message'] ?? null;
    }

    private function extractRetryAfter(ResponseInterface $response): ?int
    {
        $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;

        if (null !== $retryAfter && ctype_digit($retryAfter)) {
            return (int) $retryAfter;
        }

        return $this->extractRetryInfoDelay($response);
    }

    /**
     * Google APIs such as Gemini send no Retry-After header, but a google.rpc.RetryInfo
     * detail in the error, e.g. {"@type": "type.googleapis.com/google.rpc.RetryInfo", "retryDelay": "34.4s"}.
     */
    private function extractRetryInfoDelay(ResponseInterface $response): ?int
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        foreach ($data['error']['details'] ?? [] as $detail) {
            if (!\is_array($detail) || 'type.googleapis.com/google.rpc.RetryInfo' !== ($detail['@type'] ?? null)) {
                continue;
            }

            $retryDelay = $detail['retryDelay'] ?? null;

            // a protobuf Duration: seconds with an optional fraction, rounded up as the least time to wait
            if (\is_string($retryDelay) && preg_match('/^(\d+(?:\.\d+)?)s$/', $retryDelay, $matches)) {
                return (int) ceil((float) $matches[1]);
            }
        }

        return null;
    }
}

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
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provides shared HTTP error handling for bridge result converters.
 *
 * Translates the common 4xx/429 responses returned by AI providers into
 * dedicated platform exceptions so consumers can react to auth failures,
 * bad requests and rate limits without parsing error bodies themselves.
 *
 * Error bodies are parsed leniently: both the nested `error.message` shape
 * (OpenAI, Gemini, Perplexity) and the flat top-level `message` shape
 * (Mistral, Cerebras, Cohere) are supported.
 *
 * The `Retry-After` header lookup on 429 is best-effort: providers that
 * expose retry hints through other mechanisms (e.g. Cerebras via
 * `x-ratelimit-reset-*`, Gemini via `error.details[].retryDelay`) are not
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

        if (429 === $status) {
            throw new RateLimitExceededException($this->extractRetryAfter($response), $this->extractErrorMessage($response));
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

        if (null === $retryAfter || !ctype_digit($retryAfter)) {
            return null;
        }

        return (int) $retryAfter;
    }
}

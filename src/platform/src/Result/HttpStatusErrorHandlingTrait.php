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
 * Error bodies are parsed leniently: the nested `error.message` shape
 * (OpenAI, Gemini, Perplexity), the flat top-level `message` shape
 * (Mistral, Cerebras, Cohere) and the FastAPI `detail` shape, a message or
 * the list of violations of a 422 (Mistral, Azure), are supported.
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
            throw new AuthenticationException($this->buildHttpErrorMessage($response));
        }

        if (400 === $status || 422 === $status) {
            throw new BadRequestException($this->buildHttpErrorMessage($response));
        }

        if (404 === $status) {
            throw new ModelNotFoundException($this->buildHttpErrorMessage($response));
        }

        if (429 === $status) {
            throw new RateLimitExceededException($this->extractRetryAfter($response), $this->buildHttpErrorMessage($response));
        }

        if ($status >= 500) {
            throw new ServerException($status, $this->buildHttpErrorMessage($response));
        }
    }

    private function buildHttpErrorMessage(ResponseInterface $response): string
    {
        $message = \sprintf('HTTP %d returned', $response->getStatusCode());

        $url = $this->extractRequestUrl($response);
        if (null !== $url) {
            $message .= \sprintf(' for "%s"', $url);
        }

        $errorMessage = $this->extractErrorMessage($response);
        if (null === $errorMessage || '' === $errorMessage) {
            return $message.'.';
        }

        return $message.': '.$errorMessage;
    }

    /**
     * Drops the query and the user info, which may carry credentials (e.g. the "key" parameter of VertexAI).
     */
    private function extractRequestUrl(ResponseInterface $response): ?string
    {
        $url = $response->getInfo('url');
        if (!\is_string($url)) {
            return null;
        }

        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port.($parts['path'] ?? '');
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        $message = $data['error']['message'] ?? $data['message'] ?? null;
        if (\is_string($message)) {
            return $message;
        }

        return $this->extractDetail($data['detail'] ?? null);
    }

    private function extractDetail(mixed $detail): ?string
    {
        if (\is_string($detail)) {
            return $detail;
        }

        if (!\is_array($detail)) {
            return null;
        }

        $violations = [];
        foreach ($detail as $violation) {
            if (\is_array($violation) && \is_array($violation['loc'] ?? null) && \is_string($violation['msg'] ?? null)) {
                $violations[] = \sprintf('%s: %s', implode('.', $violation['loc']), $violation['msg']);
            }
        }

        if ([] === $violations) {
            return null;
        }

        return implode('; ', $violations);
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

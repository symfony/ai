<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi;

use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Maps Eden AI error responses onto platform exceptions.
 *
 * The shared Result\HttpStatusErrorHandlingTrait only understands the OpenAI-style
 * "error.message" and the flat "message" payloads. Eden AI is a FastAPI application and
 * reports errors through "detail" instead, in three different shapes, none of which that
 * trait can read - so an unhandled status used to surface as a misleading "Response does
 * not contain ..." error. The shapes below were captured from the live API:
 *
 *     403 {"detail": "Not authenticated"}
 *     401 {"detail": "Invalid token"}
 *     404 {"detail": {"error": "Provider not found", "message": "..."}}
 *     400 {"detail": {"error": "Invalid provider format", "message": "...", "example": "..."}}
 *     422 {"detail": "Validation error", "errors": [{"field": "language", "message": "Field required"}]}
 *
 * The OpenAI-compatible endpoints (/v3/chat/completions, /v3/embeddings) keep the
 * "error.message" shape, which is why both are read here.
 *
 * Note that the published OpenAPI schema describes 422 as FastAPI's default
 * "detail: [{loc, msg, type}]" list, which the API does not actually emit; that form is
 * still parsed defensively.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
trait ErrorHandlingTrait
{
    /**
     * @param list<int> $expectedStatusCodes the success codes for this endpoint: the
     *                                       asynchronous endpoint answers 202, not 200
     */
    private function assertSuccessfulResponse(ResponseInterface $response, array $expectedStatusCodes = [200]): void
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Could not reach Eden AI.', 0, $e);
        }

        if (\in_array($statusCode, $expectedStatusCodes, true)) {
            return;
        }

        $message = $this->extractEdenAiErrorMessage($response);

        if (401 === $statusCode || 403 === $statusCode) {
            throw new AuthenticationException($message ?? 'Authentication failed.');
        }

        if (400 === $statusCode || 422 === $statusCode) {
            throw new BadRequestException($message ?? 'Bad Request');
        }

        if (404 === $statusCode) {
            throw new ModelNotFoundException($message ?? 'Not Found');
        }

        if (429 === $statusCode) {
            throw new RateLimitExceededException($this->extractRetryAfterHeader($response), $message);
        }

        if ($statusCode >= 500) {
            throw new ServerException($statusCode, $message);
        }

        if (null !== $message) {
            throw new RuntimeException(\sprintf('Unexpected response code %d from Eden AI: "%s"', $statusCode, $message));
        }

        throw new RuntimeException(\sprintf('Unexpected response code %d from Eden AI.', $statusCode));
    }

    /**
     * Reads every observed Eden AI error shape, plus the OpenAI-compatible one.
     */
    private function extractEdenAiErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface|TransportExceptionInterface) {
            return null;
        }

        $message = $this->extractPrimaryErrorMessage($data);
        $fieldErrors = $this->extractFieldErrors($data);

        if ([] === $fieldErrors) {
            return $message;
        }

        $joined = implode(', ', $fieldErrors);

        if (null === $message) {
            return $joined;
        }

        return $message.': '.$joined;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractPrimaryErrorMessage(array $data): ?string
    {
        // OpenAI-compatible endpoints, and providers relaying an OpenAI-style body.
        if (isset($data['error']['message']) && \is_string($data['error']['message'])) {
            return $data['error']['message'];
        }

        if (isset($data['error']) && \is_string($data['error'])) {
            return $data['error'];
        }

        $detail = $data['detail'] ?? null;

        if (\is_string($detail) && '' !== $detail) {
            return $detail;
        }

        // {"detail": {"error": "Provider not found", "message": "'x' does not support ocr/ocr"}}
        if (\is_array($detail) && isset($detail['message']) && \is_string($detail['message'])) {
            if (isset($detail['error']) && \is_string($detail['error'])) {
                return $detail['error'].': '.$detail['message'];
            }

            return $detail['message'];
        }

        if (isset($data['message']) && \is_string($data['message'])) {
            return $data['message'];
        }

        return null;
    }

    /**
     * Flattens the per-field validation errors of a 422 into "field: reason" pairs.
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function extractFieldErrors(array $data): array
    {
        $fieldErrors = [];

        // Live shape: {"errors": [{"field": "language", "message": "Field required"}]}
        if (isset($data['errors']) && \is_array($data['errors'])) {
            foreach ($data['errors'] as $error) {
                if (!\is_array($error)) {
                    continue;
                }

                $field = $error['field'] ?? null;
                $reason = $error['message'] ?? null;

                if (!\is_string($reason)) {
                    continue;
                }

                if (\is_string($field)) {
                    $fieldErrors[] = $field.': '.$reason;
                } else {
                    $fieldErrors[] = $reason;
                }
            }
        }

        // Schema-documented shape: {"detail": [{"loc": ["body", "model"], "msg": "...", "type": "..."}]}
        if (isset($data['detail']) && \is_array($data['detail']) && array_is_list($data['detail'])) {
            foreach ($data['detail'] as $violation) {
                if (!\is_array($violation) || !isset($violation['msg']) || !\is_string($violation['msg'])) {
                    continue;
                }

                $location = $violation['loc'] ?? null;

                if (\is_array($location) && [] !== $location) {
                    $fieldErrors[] = implode('.', array_map(static fn (mixed $part): string => (string) $part, $location)).': '.$violation['msg'];
                } else {
                    $fieldErrors[] = $violation['msg'];
                }
            }
        }

        return $fieldErrors;
    }

    private function extractRetryAfterHeader(ResponseInterface $response): ?int
    {
        try {
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
        } catch (TransportExceptionInterface) {
            return null;
        }

        if (null === $retryAfter || !ctype_digit($retryAfter)) {
            return null;
        }

        return (int) $retryAfter;
    }
}

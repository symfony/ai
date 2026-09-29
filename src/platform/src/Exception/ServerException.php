<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

/**
 * Thrown when a provider returns a transient server-side error (HTTP 5xx) or
 * emits a server-side error event mid-stream (e.g. Anthropic "overloaded").
 *
 * These failures are usually transient, so consumers may want to retry the
 * request. The status code is null for mid-stream error events, which carry no
 * HTTP status.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ServerException extends RuntimeException implements RetryableExceptionInterface
{
    public function __construct(
        private readonly ?int $statusCode = null,
        ?string $errorMessage = null,
    ) {
        $message = null !== $statusCode
            ? \sprintf('Server error (HTTP %d).', $statusCode)
            : 'Server error.';

        if (null !== $errorMessage && '' !== $errorMessage) {
            $message .= ' '.$errorMessage;
        }

        parent::__construct($message);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}

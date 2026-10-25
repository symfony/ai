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
 * @author Floran Pagliai <floran.pagliai@gmail.com>
 */
final class RateLimitExceededException extends RuntimeException implements ErrorCodeAwareInterface, RetryableExceptionInterface
{
    public function __construct(
        private readonly ?int $retryAfter = null,
        ?string $errorMessage = null,
    ) {
        $message = 'Rate limit exceeded.';
        if (null !== $errorMessage && '' !== $errorMessage) {
            $message .= ' '.$errorMessage;
        }

        parent::__construct($message);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function getErrorCode(): string
    {
        return 'rate_limit_exceeded';
    }

    public function isRetryable(): bool
    {
        return true;
    }
}

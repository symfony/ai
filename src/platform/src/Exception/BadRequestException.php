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
 * @author Oscar Esteve <oscarsdt@gmail.com>
 */
class BadRequestException extends RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly ?string $errorCode = null,
        private readonly ?string $errorType = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The machine-readable code the provider sent with the error, e.g. "tool_use_failed" on Groq.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * The error type the provider sent with the error, e.g. "invalid_request_error".
     */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Exception;

final class InvalidToolCallArgumentsException extends \RuntimeException implements ToolExecutionExceptionInterface
{
    public function __construct(string $message = 'Invalid tool call arguments', int $code = 0, ?\Throwable $previous = null, private mixed $toolCallResult = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getToolCallResult(): mixed
    {
        return $this->toolCallResult ?? $this->message;
    }
}

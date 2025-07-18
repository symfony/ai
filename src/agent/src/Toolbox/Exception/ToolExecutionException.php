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

use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolExecutionException extends \RuntimeException implements ExceptionInterface
{
    public ?ToolCall $toolCall = null;

    public static function executionFailed(ToolCall $toolCall, \Throwable $previous): self
    {
        $exception = new self(\sprintf('Execution of tool "%s" failed with error: %s', $toolCall->name, $previous->getMessage()), previous: $previous);
        $exception->toolCall = $toolCall;

        return $exception;
    }
}

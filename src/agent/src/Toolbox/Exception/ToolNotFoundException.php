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
use Symfony\AI\Platform\Tool\ExecutionReference;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolNotFoundException extends \RuntimeException implements ExceptionInterface
{
    private ?ToolCall $toolCall = null;

    public static function notFoundForToolCall(ToolCall $toolCall): self
    {
        $exception = new self(\sprintf('Tool not found for call: %s.', $toolCall->getName()));
        $exception->toolCall = $toolCall;

        return $exception;
    }

    public static function notFoundForReference(ExecutionReference $reference): self
    {
        return new self(\sprintf('Tool not found for reference: %s::%s.', $reference->getClass(), $reference->getMethod()));
    }

    public function getToolCall(): ?ToolCall
    {
        return $this->toolCall;
    }
}

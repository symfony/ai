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

/**
 * Tells the model why a tool call failed: its message is the result of the call.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ToolErrorException extends \RuntimeException implements ToolExecutionExceptionInterface
{
    public function getToolCallResult(): string
    {
        return $this->getMessage();
    }
}

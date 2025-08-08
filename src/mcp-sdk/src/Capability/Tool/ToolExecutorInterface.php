<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Tool;

use Symfony\AI\McpSdk\Exception\ToolExecutionException;
use Symfony\AI\McpSdk\Exception\ToolNotFoundException;
use Symfony\AI\McpSdk\Message\Notification;

interface ToolExecutorInterface
{
    /**
     * @return ToolCallResult|\Traversable<Notification|ToolCallResult>
     * @throws ToolExecutionException if the tool execution fails
     * @throws ToolNotFoundException  if the tool is not found
     */
    public function call(ToolCall $input): ToolCallResult|\Traversable;
}

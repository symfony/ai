<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpLlm\McpSdk\Capability\Tool;

use PhpLlm\McpSdk\Exception\ToolExecutionException;
use PhpLlm\McpSdk\Exception\ToolNotFoundException;

interface ToolExecutorInterface
{
    /**
     * @throws ToolExecutionException if the tool execution fails
     * @throws ToolNotFoundException  if the tool is not found
     */
    public function call(ToolCall $input): ToolCallResult;
}

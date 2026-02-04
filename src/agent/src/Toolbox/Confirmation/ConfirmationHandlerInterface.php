<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Confirmation;

use Symfony\AI\Platform\Result\ToolCall;

/**
 * Handles user confirmation requests for tool execution.
 *
 * Implementations can provide CLI prompts, web UI dialogs, or other
 * mechanisms to ask the user for permission.
 *
 * Note: This method blocks the tool execution pipeline until a result is
 * returned. Implementations should consider adding a timeout mechanism
 * to avoid blocking indefinitely, especially in web or async contexts.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ConfirmationHandlerInterface
{
    public function requestConfirmation(ToolCall $toolCall): ConfirmationResult;
}

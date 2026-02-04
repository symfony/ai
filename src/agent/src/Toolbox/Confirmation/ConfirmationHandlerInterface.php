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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ConfirmationHandlerInterface
{
    public function requestConfirmation(ToolCall $toolCall): ConfirmationResult;
}

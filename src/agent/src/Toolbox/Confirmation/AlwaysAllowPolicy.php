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
 * Policy that always allows tool execution without confirmation.
 * Use with caution - this bypasses all safety checks.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AlwaysAllowPolicy implements PolicyInterface
{
    public function decide(ToolCall $toolCall): PolicyDecision
    {
        return PolicyDecision::Allow;
    }
}

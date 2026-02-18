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
 * Determines whether a tool call should be allowed, denied, or require user confirmation.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface PolicyInterface
{
    public function decide(ToolCall $toolCall): PolicyDecision;
}

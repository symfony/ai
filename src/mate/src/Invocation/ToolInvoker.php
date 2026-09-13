<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Invocation;

use Symfony\AI\Mate\Discovery\Model\ToolDefinition;

/**
 * Invokes a discovered tool's handler method, resolving the handler instance from the DI
 * container when available and mapping/casting arguments from the provided bag.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolInvoker
{
    public function __construct(
        private HandlerInvoker $handlerInvoker,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function invoke(ToolDefinition $tool, array $arguments): mixed
    {
        return $this->handlerInvoker->call($tool->handlerClass, $tool->handlerMethod, $arguments);
    }
}

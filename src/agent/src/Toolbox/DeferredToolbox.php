<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Marks tools of the decorated toolbox as deferred, so they are only made available to the model through a tool search.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DeferredToolbox implements ToolboxInterface, ResetInterface
{
    /**
     * @param list<string>|null $toolNames names of the tools to defer, all tools of the decorated toolbox if null
     */
    public function __construct(
        private readonly ToolboxInterface $innerToolbox,
        private readonly ?array $toolNames = null,
    ) {
    }

    public function getTools(): array
    {
        return array_map(
            fn (Tool $tool): Tool => null === $this->toolNames || \in_array($tool->getName(), $this->toolNames, true) ? $tool->withDeferred() : $tool,
            $this->innerToolbox->getTools(),
        );
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        return $this->innerToolbox->execute($toolCall);
    }

    public function reset(): void
    {
        if ($this->innerToolbox instanceof ResetInterface) {
            $this->innerToolbox->reset();
        }
    }
}

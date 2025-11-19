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

use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Catches exceptions thrown by the inner tool box and returns error messages for the LLM instead.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FaultTolerantToolbox implements ToolboxInterface
{
    public function __construct(
        private readonly ToolboxInterface $innerToolbox,
    ) {
    }

    public function getTools(): array
    {
        return $this->innerToolbox->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        try {
            return $this->innerToolbox->execute($toolCall);
        } catch (ToolExecutionExceptionInterface $e) {
            return new ToolResult($toolCall, $e->getToolCallResult());
        } catch (ToolNotFoundException) {
            $names = array_map(fn (Tool $metadata) => $metadata->getName(), $this->getTools());

            return new ToolResult(
                $toolCall,
                \sprintf('Tool "%s" was not found, please use one of these: %s', $toolCall->getName(), implode(', ', $names))
            );
        }
    }
}

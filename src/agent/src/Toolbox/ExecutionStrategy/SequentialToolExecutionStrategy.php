<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\ExecutionStrategy;

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Executes tool calls one after another in a simple foreach loop.
 *
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class SequentialToolExecutionStrategy implements ToolExecutionStrategyInterface
{
    /**
     * @param ToolCall[] $toolCalls
     *
     * @return ToolResult[]
     */
    public function execute(ToolboxInterface $toolbox, array $toolCalls): array
    {
        $results = [];
        foreach ($toolCalls as $toolCall) {
            $results[] = $toolbox->execute($toolCall);
        }

        return $results;
    }
}

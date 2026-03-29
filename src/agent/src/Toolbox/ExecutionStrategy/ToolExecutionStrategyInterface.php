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
 * Defines a strategy for executing a collection of tool calls returned by the LLM.
 *
 * Implementations can choose any execution model, such as sequential iteration,
 * PHP Fibers for cooperative multitasking, or background process spawning.
 *
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
interface ToolExecutionStrategyInterface
{
    /**
     * Executes all given tool calls using the provided toolbox.
     *
     * @param ToolCall[] $toolCalls
     *
     * @return ToolResult[]
     */
    public function execute(ToolboxInterface $toolbox, array $toolCalls): array;
}

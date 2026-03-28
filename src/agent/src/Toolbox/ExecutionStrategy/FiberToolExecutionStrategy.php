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
 * Executes tool calls concurrently using PHP Fibers for cooperative multitasking.
 *
 * All fibers are started before any result is collected, allowing tool
 * implementations that suspend execution (e.g. via Fiber::suspend()) to
 * interleave their work. This is beneficial for I/O-bound tools, but note
 * that PHP Fibers do not provide OS-level parallelism within a single process.
 *
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class FiberToolExecutionStrategy implements ToolExecutionStrategyInterface
{
    /**
     * @param ToolCall[] $toolCalls
     *
     * @return ToolResult[]
     */
    public function execute(ToolboxInterface $toolbox, array $toolCalls): array
    {
        $fibers = [];
        foreach ($toolCalls as $toolCall) {
            $fiber = new \Fiber(static fn () => $toolbox->execute($toolCall));
            $fiber->start();
            $fibers[] = $fiber;
        }

        $results = [];
        while ([] !== $fibers) {
            foreach ($fibers as $key => $fiber) {
                if ($fiber->isTerminated()) {
                    $results[] = $fiber->getReturn();
                    unset($fibers[$key]);
                } else {
                    $fiber->resume();
                }
            }
        }

        return $results;
    }
}

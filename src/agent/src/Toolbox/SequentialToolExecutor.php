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

use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Executes the requested tool calls one after another.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SequentialToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    /**
     * @param ToolCall[] $toolCalls
     *
     * @return \Generator<int, UpdateInterface, mixed, ToolResult[]>
     */
    public function execute(array $toolCalls): \Generator
    {
        $results = [];
        foreach ($toolCalls as $toolCall) {
            yield new Progress('tool_call', \sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall);

            $results[] = $this->toolbox->execute($toolCall);
        }

        return $results;
    }
}

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

use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Executes the tool calls a model requested and returns their results in the same order.
 *
 * Turning the results into conversation messages is the caller's responsibility, so an executor only
 * decides how the calls are run (sequentially, concurrently, remotely, ...).
 *
 * The returned generator yields {@see UpdateInterface} updates while the tool calls are executed and
 * returns the resulting {@see ToolResult} list.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ToolExecutorInterface
{
    /**
     * @param ToolCall[] $toolCalls
     *
     * @return \Generator<int, UpdateInterface, mixed, ToolResult[]> one result per tool call, in the same order as the given calls
     */
    public function execute(array $toolCalls): \Generator;
}

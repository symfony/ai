<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream\Delta;

use Symfony\AI\Platform\Result\ToolCall;

/**
 * Signals that all tool calls in the stream are complete and ready for execution.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ToolCallComplete implements DeltaInterface
{
    /**
     * @var ToolCall[]
     */
    private readonly array $toolCalls;

    public function __construct(ToolCall ...$toolCalls)
    {
        $this->toolCalls = $toolCalls;
    }

    /**
     * @return ToolCall[]
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }
}

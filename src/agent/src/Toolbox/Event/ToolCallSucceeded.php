<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Event;

use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Dispatched after successfully invoking a tool.
 */
final class ToolCallSucceeded
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private readonly object $tool,
        private readonly Tool $definition,
        private readonly array $arguments,
        private readonly ToolResult $result,
    ) {
    }

    public function getTool(): object
    {
        return $this->tool;
    }

    public function getDefinition(): Tool
    {
        return $this->definition;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getResult(): ToolResult
    {
        return $this->result;
    }
}

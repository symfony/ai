<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Keeps the profiler from connecting to an MCP server just to list its tools.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DeferredToolbox implements ToolboxInterface, ResetInterface
{
    /**
     * @var Tool[]|null
     */
    private ?array $tools = null;

    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    public function getTools(): array
    {
        return $this->tools ??= $this->toolbox->getTools();
    }

    /**
     * The tools already listed, without asking the inner toolbox for them.
     *
     * @return Tool[]
     */
    public function getLoadedTools(): array
    {
        return $this->tools ?? [];
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        return $this->toolbox->execute($toolCall);
    }

    /**
     * A worker drops its connections between messages, so nothing stays listed across them.
     */
    public function reset(): void
    {
        $this->tools = null;
    }
}

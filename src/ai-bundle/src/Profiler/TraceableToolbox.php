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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TraceableToolbox implements ToolboxInterface
{
    /**
     * @var ToolResult[]
     */
    public array $calls = [];

    private bool $toolsLoaded = false;

    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    public function getTools(): array
    {
        $this->toolsLoaded = true;

        return $this->toolbox->getTools();
    }

    /**
     * Get tools only if already loaded (lazy-safe for profiling).
     *
     * This prevents triggering remote MCP connections during profiler
     * data collection if the toolbox wasn't actually used during the request.
     *
     * @return array<\Symfony\AI\Platform\Tool\Tool>
     */
    public function getToolsIfLoaded(): array
    {
        if (!$this->toolsLoaded) {
            return [];
        }

        return $this->toolbox->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        return $this->calls[] = $this->toolbox->execute($toolCall);
    }
}

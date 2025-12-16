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

use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Combines multiple toolboxes into a single toolbox.
 *
 * This allows using tools from multiple sources (local tools, MCP servers, etc.)
 * as if they were a single toolbox.
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
final class ChainToolbox implements ToolboxInterface
{
    /** @var ToolboxInterface[] */
    private readonly array $toolboxes;

    /** @var array<string, ToolboxInterface>|null */
    private ?array $toolIndex = null;

    /**
     * @param ToolboxInterface[] $toolboxes
     */
    public function __construct(array $toolboxes)
    {
        $this->toolboxes = $toolboxes;
    }

    public function getTools(): array
    {
        $tools = [];
        $this->toolIndex = [];

        foreach ($this->toolboxes as $toolbox) {
            foreach ($toolbox->getTools() as $tool) {
                $toolName = $tool->getName();

                if (isset($this->toolIndex[$toolName])) {
                    throw new LogicException(\sprintf('Tool "%s" is already registered in another toolbox.', $toolName));
                }

                $tools[] = $tool;
                $this->toolIndex[$toolName] = $toolbox;
            }
        }

        return $tools;
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $toolbox = $this->findToolbox($toolCall);

        return $toolbox->execute($toolCall);
    }

    private function findToolbox(ToolCall $toolCall): ToolboxInterface
    {
        if (null === $this->toolIndex) {
            $this->getTools();
        }

        $toolName = $toolCall->getName();

        if (isset($this->toolIndex[$toolName])) {
            return $this->toolIndex[$toolName];
        }

        throw ToolNotFoundException::notFoundForToolCall($toolCall);
    }
}

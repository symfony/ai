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

use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Offers the tools of several toolboxes as one, running a call on the toolbox advertising it.
 *
 * A tool name advertised by more than one toolbox is refused instead of picking one of them.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChainToolbox implements ToolboxInterface
{
    /**
     * The toolbox advertising each tool name, as of the latest listing.
     *
     * @var array<string, array{tool: Tool, toolbox: ToolboxInterface}>|null
     */
    private ?array $owners = null;

    /**
     * @param iterable<ToolboxInterface> $toolboxes
     */
    public function __construct(
        private readonly iterable $toolboxes,
    ) {
    }

    public function getTools(): array
    {
        // Cleared first, so a refused listing does not leave calls to the record of an earlier one.
        $this->owners = null;

        $collected = $this->collectTools();
        $this->owners = $collected['owners'];

        return $collected['tools'];
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        // A call answers the latest listing, which is what the model was offered. Listing every
        // toolbox again per call would re-contact a server that failed to list on each local call.
        $owners = $this->owners ?? $this->collectTools()['owners'];

        if (!isset($owners[$toolCall->getName()])) {
            throw ToolNotFoundException::notFoundForToolCall($toolCall);
        }

        return $owners[$toolCall->getName()]['toolbox']->execute($toolCall);
    }

    /**
     * Lists every toolbox, so a tool name offered twice fails whichever of them is called.
     *
     * @return array{
     *     tools: list<Tool>,
     *     owners: array<string, array{tool: Tool, toolbox: ToolboxInterface}>,
     * }
     *
     * @throws ToolConfigurationException if more than one toolbox advertises the same tool name
     */
    private function collectTools(): array
    {
        $tools = [];
        $owners = [];
        foreach ($this->toolboxes as $toolbox) {
            foreach ($toolbox->getTools() as $tool) {
                $name = $tool->getName();

                if (!isset($owners[$name])) {
                    $owners[$name] = ['tool' => $tool, 'toolbox' => $toolbox];
                } elseif ($owners[$name]['toolbox'] !== $toolbox) {
                    throw ToolConfigurationException::duplicateToolName($name, $owners[$name]['toolbox'], $owners[$name]['tool'], $toolbox, $tool);
                }

                $tools[] = $tool;
            }
        }

        return ['tools' => $tools, 'owners' => $owners];
    }
}

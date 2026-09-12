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

use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Offers the tools of several toolboxes as one, running a call on the first toolbox advertising it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChainToolbox implements ToolboxInterface
{
    /**
     * @param iterable<ToolboxInterface> $toolboxes
     */
    public function __construct(
        private readonly iterable $toolboxes,
    ) {
    }

    public function getTools(): array
    {
        $tools = [];
        foreach ($this->toolboxes as $toolbox) {
            foreach ($toolbox->getTools() as $metadata) {
                $tools[] = $metadata;
            }
        }

        return $tools;
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        foreach ($this->toolboxes as $toolbox) {
            foreach ($toolbox->getTools() as $metadata) {
                if ($metadata->getName() === $toolCall->getName()) {
                    return $toolbox->execute($toolCall);
                }
            }
        }

        throw ToolNotFoundException::notFoundForToolCall($toolCall);
    }
}

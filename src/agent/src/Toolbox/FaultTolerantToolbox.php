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

use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Response\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Catches exceptions thrown by the inner tool box and returns error messages for the LLM instead.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class FaultTolerantToolbox implements ToolboxInterface
{
    public function __construct(
        private ToolboxInterface $innerToolbox,
    ) {
    }

    public function getTools(): array
    {
        return $this->innerToolbox->getTools();
    }

    public function execute(ToolCall $toolCall): mixed
    {
        try {
            return $this->innerToolbox->execute($toolCall);
        } catch (ToolExecutionException $e) {
            return \sprintf('An error occurred while executing tool "%s".', $e->toolCall->name);
        } catch (ToolNotFoundException) {
            $names = array_map(fn (Tool $metadata) => $metadata->name, $this->getTools());

            return \sprintf('Tool "%s" was not found, please use one of these: %s', $toolCall->name, implode(', ', $names));
        }
    }
}

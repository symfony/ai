<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\SleepTime\Tool;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\SleepTime\MemoryBlock;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Tool used by the sleeping agent to update shared memory blocks with insights
 * derived from conversation analysis.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
#[AsTool('rethink_memory', 'Update a memory block with new insights derived from analyzing the conversation. Use this to store important facts, user preferences, and contextual information for future interactions.')]
final class RethinkMemory
{
    /**
     * @param MemoryBlock[] $memoryBlocks
     */
    public function __construct(
        private readonly array $memoryBlocks,
    ) {
    }

    /**
     * @return string Confirmation message
     */
    public function __invoke(string $label, string $content): string
    {
        foreach ($this->memoryBlocks as $block) {
            if ($block->getLabel() === $label) {
                $block->setContent($content);

                return \sprintf('Memory block "%s" updated successfully.', $label);
            }
        }

        $availableLabels = array_map(
            static fn (MemoryBlock $block): string => $block->getLabel(),
            $this->memoryBlocks,
        );

        throw new InvalidArgumentException(\sprintf('Memory block "%s" not found. Available labels: "%s".', $label, implode('", "', $availableLabels)));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\SleepTime;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Memory\Memory;
use Symfony\AI\Agent\Memory\MemoryProviderInterface;

/**
 * Memory provider that injects sleep-time enriched memory blocks into the agent context.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MemoryBlockProvider implements MemoryProviderInterface
{
    /**
     * @param MemoryBlock[] $memoryBlocks
     */
    public function __construct(
        private readonly array $memoryBlocks,
    ) {
    }

    public function load(Input $input): array
    {
        $content = '';

        foreach ($this->memoryBlocks as $block) {
            if ('' === $block->getContent()) {
                continue;
            }

            $content .= \sprintf("## %s\n%s\n\n", $block->getLabel(), $block->getContent());
        }

        if ('' === $content) {
            return [];
        }

        return [new Memory('## Sleep-Time Memory'.\PHP_EOL.\PHP_EOL.$content)];
    }
}

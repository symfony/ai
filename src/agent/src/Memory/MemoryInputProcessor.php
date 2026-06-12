<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Memory;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Platform\Message\Message;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class MemoryInputProcessor implements InputProcessorInterface
{
    /**
     * Metadata key on the combined system message that preserves the original
     * system prompt, so repeated runs on the same message bag recombine from
     * the original instead of nesting the memory prompt into itself.
     */
    private const ORIGINAL_SYSTEM_PROMPT_KEY = 'memory_original_system_prompt';

    private const MEMORY_PROMPT_MESSAGE = <<<MARKDOWN
        # Conversation Memory
        This is the memory I have found for this conversation. The memory has more weight to answer user input,
        so try to answer utilizing the memory as much as possible. Your answer must be changed to fit the given
        memory. If the memory is irrelevant, ignore it. Do not reply to the this section of the prompt and do not
        reference it as this is just for your reference.
        MARKDOWN;

    /**
     * @param iterable<MemoryProviderInterface> $memoryProviders
     */
    public function __construct(
        private readonly iterable $memoryProviders,
    ) {
    }

    public function processInput(Input $input): void
    {
        $options = $input->getOptions();
        $useMemory = $options['use_memory'] ?? true;
        unset($options['use_memory']);
        $input->setOptions($options);

        if (false === $useMemory || 0 === \count($this->memoryProviders)) {
            return;
        }

        $memory = '';
        foreach ($this->memoryProviders as $provider) {
            $memoryMessages = $provider->load($input);

            if (0 === \count($memoryMessages)) {
                continue;
            }

            $memory .= \PHP_EOL.\PHP_EOL;
            $memory .= implode(
                \PHP_EOL,
                array_map(static fn (Memory $memory): string => $memory->getContent(), $memoryMessages),
            );
        }

        if ('' === $memory) {
            return;
        }

        $systemMessage = $input->getMessageBag()->getSystemMessage();
        $originalSystemPrompt = $systemMessage?->getMetadata()->get(self::ORIGINAL_SYSTEM_PROMPT_KEY);
        if (!\is_string($originalSystemPrompt)) {
            $originalSystemPrompt = $systemMessage?->getContent() ?? '';
        }

        $combinedMessage = self::MEMORY_PROMPT_MESSAGE.$memory;
        if ('' !== $originalSystemPrompt) {
            $combinedMessage .= \PHP_EOL.\PHP_EOL.'# System Prompt'.\PHP_EOL.\PHP_EOL.$originalSystemPrompt;
        }

        $combinedSystemMessage = Message::forSystem($combinedMessage);
        $combinedSystemMessage->getMetadata()->add(self::ORIGINAL_SYSTEM_PROMPT_KEY, $originalSystemPrompt);

        $messages = $input->getMessageBag();
        $messages->removeSystemMessage();
        $messages->prepend($combinedSystemMessage);
    }
}

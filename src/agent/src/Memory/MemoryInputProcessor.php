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
    private const MEMORY_PROMPT_MESSAGE = <<<MARKDOWN
        # Conversation Memory
        This is the memory I have found for this conversation. The memory has more weight to answer user input,
        so try to answer utilizing the memory as much as possible. Your answer must be changed to fit the given
        memory. If the memory is irrelevant, ignore it. Do not reply to the this section of the prompt and do not
        reference it as this is just for your reference.
        MARKDOWN;

    /**
     * @var MemoryProviderInterface[]
     */
    private readonly array $memoryProviders;

    public function __construct(
        MemoryProviderInterface ...$memoryProviders,
    ) {
        $this->memoryProviders = $memoryProviders;
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

        $systemMessage = $input->getMessageBag()->getSystemMessage()?->getContent() ?? '';

        $combinedMessage = self::MEMORY_PROMPT_MESSAGE.$memory;
        if ('' !== $systemMessage) {
            $combinedMessage .= \PHP_EOL.\PHP_EOL.'# System Prompt'.\PHP_EOL.\PHP_EOL.$systemMessage;
        }

        $messages = $input->getMessageBag()
            ->withSystemMessage(Message::forSystem($combinedMessage));

        $input->setMessageBag($messages);
    }
}

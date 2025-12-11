<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\InputProcessor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SystemPromptInputProcessor implements InputProcessorInterface
{
    /**
     * @param \Stringable|TranslatableInterface|string|File $systemPrompt the system prompt to prepend to the input messages, or a File object to read from
     * @param ToolboxInterface|null                         $toolbox      the tool box to be used to append the tool definitions to the system prompt
     */
    public function __construct(
        private readonly \Stringable|TranslatableInterface|string|File $systemPrompt,
        private readonly ?ToolboxInterface $toolbox = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        if ($this->systemPrompt instanceof TranslatableInterface && !$this->translator) {
            throw new RuntimeException('Translatable system prompt is not supported when no translator is provided.');
        }
    }

    public function processInput(Input $input): void
    {
        $messages = $input->getMessageBag();

        if (null !== $messages->getSystemMessage()) {
            $this->logger->debug('Skipping system prompt injection since MessageBag already contains a system message.');

            return;
        }

        if ($this->systemPrompt instanceof File) {
            $message = $this->systemPrompt->asBinary();
        } elseif ($this->systemPrompt instanceof TranslatableInterface) {
            $message = $this->systemPrompt->trans($this->translator);
        } else {
            $message = (string) $this->systemPrompt;
        }

        if ($this->toolbox instanceof ToolboxInterface
            && [] !== $this->toolbox->getTools()
        ) {
            $this->logger->debug('Append tool definitions to system prompt.');

            $tools = implode(\PHP_EOL.\PHP_EOL, array_map(
                fn (Tool $tool) => <<<TOOL
                    ## {$tool->getName()}
                    {$tool->getDescription()}
                    TOOL,
                $this->toolbox->getTools()
            ));

            $message = <<<PROMPT
                {$message}

                # Tools

                The following tools are available to assist you in completing the user's request:

                {$tools}
                PROMPT;
        }

        $input->setMessageBag($messages->withSystemMessage(Message::forSystem($message)));
    }
}

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
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class SystemPromptInputProcessor implements InputProcessorInterface
{
    /**
     * @param \Stringable|string    $systemPrompt the system prompt to prepend to the input messages
     * @param ToolboxInterface|null $toolbox      the tool box to be used to append the tool definitions to the system prompt
     */
    public function __construct(
        private \Stringable|string $systemPrompt,
        private ?ToolboxInterface $toolbox = null,
        private ?TranslatorInterface $translator = null,
        private bool $enableTranslation = false,
        private ?string $translationDomain = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        if ($this->enableTranslation && !$this->translator) {
            throw new RuntimeException('Prompt translation is enabled but no translator was provided.');
        }
    }

    public function processInput(Input $input): void
    {
        $messages = $input->messages;

        if (null !== $messages->getSystemMessage()) {
            $this->logger->debug('Skipping system prompt injection since MessageBag already contains a system message.');

            return;
        }

        $message = $this->enableTranslation
            ? $this->translator->trans((string) $this->systemPrompt, [], $this->translationDomain)
            : (string) $this->systemPrompt;

        if ($this->toolbox instanceof ToolboxInterface
            && [] !== $this->toolbox->getTools()
        ) {
            $this->logger->debug('Append tool definitions to system prompt.');

            $tools = implode(\PHP_EOL.\PHP_EOL, array_map(
                fn (Tool $tool) => <<<TOOL
                    ## {$tool->name}
                    {$tool->description}
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

        $input->messages = $messages->prepend(Message::forSystem($message));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context\Processor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Injects the agent {@see Instruction}(s) from the context as the system message.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InstructionProcessor implements ContextProcessorInterface
{
    /**
     * @param ToolboxInterface|null $toolbox the toolbox whose tool definitions get appended to the instruction
     */
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?ToolboxInterface $toolbox = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public static function supportedTypes(): array
    {
        return [Instruction::class];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        $messages = $request->getMessageBag();

        if (null !== $messages->getSystemMessage()) {
            $this->logger->debug('Skipping instruction injection since MessageBag already contains a system message.');

            return;
        }

        $instructions = $request->getContext()->all(Instruction::class);
        if ([] === $instructions) {
            return;
        }

        $rendered = [];
        foreach ($instructions as $instruction) {
            \assert($instruction instanceof Instruction);
            $rendered[] = $this->render($instruction->getContent());
        }

        // mutate the caller's bag in place, so the injected system message ends up in it
        $messages->prepend(Message::forSystem($this->appendTools($this->combine($rendered))));
    }

    /**
     * @param list<string|Template> $rendered
     */
    private function combine(array $rendered): string|Template
    {
        if (1 === \count($rendered)) {
            return $rendered[0];
        }

        foreach ($rendered as $instruction) {
            if ($instruction instanceof Template) {
                throw new RuntimeException('A template instruction cannot be combined with other instructions.');
            }
        }

        return implode(\PHP_EOL.\PHP_EOL, $rendered);
    }

    private function appendTools(string|Template $instruction): string|Template
    {
        if (!$this->toolbox instanceof ToolboxInterface || [] === $this->toolbox->getTools()) {
            return $instruction;
        }

        $this->logger->debug('Append tool definitions to the instruction.');

        $tools = implode(\PHP_EOL.\PHP_EOL, array_map(
            static fn (Tool $tool): string => <<<TOOL
                ## {$tool->getName()}
                {$tool->getDescription()}
                TOOL,
            $this->toolbox->getTools(),
        ));

        if ($instruction instanceof Template) {
            $toolsPrompt = \PHP_EOL.\PHP_EOL
                .'# Tools'.\PHP_EOL.\PHP_EOL
                ."The following tools are available to assist you in completing the user's request:".\PHP_EOL.\PHP_EOL
                .$tools;

            // an expression template is code, so the tools are concatenated as a string literal
            $template = 'expression' === $instruction->getType()
                ? '('.$instruction->getTemplate().') ~ '.json_encode($toolsPrompt, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)
                : $instruction->getTemplate().$toolsPrompt;

            return new Template($template, $instruction->getType());
        }

        return <<<PROMPT
            {$instruction}

            # Tools

            The following tools are available to assist you in completing the user's request:

            {$tools}
            PROMPT;
    }

    private function render(string|\Stringable|TranslatableInterface|File|Template $content): string|Template
    {
        if ($content instanceof Template) {
            return $content;
        }

        if ($content instanceof File) {
            return $content->asBinary();
        }

        if ($content instanceof TranslatableInterface) {
            if (null === $this->translator) {
                throw new RuntimeException('Translatable instruction is not supported when no translator is provided.');
            }

            return $content->trans($this->translator);
        }

        return (string) $content;
    }
}

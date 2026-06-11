<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tui\Command;

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SelectionChangeEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Chat with any configured agent in a rich terminal UI, built on the
 * experimental Symfony TUI component. The plain-text counterpart is the
 * bundle's `ai:chat` (ai:agent:call) command.
 *
 * Run with `-v` (or higher) to open a debug pane that logs each turn's tool
 * calls and token usage as the agent runs.
 *
 * Widget/layout patterns here mirror the symfony/tui example project at
 * https://github.com/mattleads/TuiComponent (bordered panes via a StyleSheet,
 * an expanding content pane with the input docked beneath it, and a hint line).
 */
#[AsCommand(
    name: 'app:tui',
    description: 'Chat with an agent in a rich terminal UI (Symfony TUI component).',
)]
final class ChatTuiCommand extends Command
{
    /**
     * @param ServiceProviderInterface<AgentInterface> $agents
     */
    public function __construct(
        #[AutowireLocator('ai.agent', indexAttribute: 'name')]
        private readonly ServiceProviderInterface $agents,
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('agent')) {
            $suggestions->suggestValues(array_keys($this->agents->getProvidedServices()));
        }
    }

    protected function configure(): void
    {
        $this->addArgument('agent', InputArgument::OPTIONAL, 'The name of the agent to chat with. If omitted, you can pick one in the UI.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agentNames = array_keys($this->agents->getProvidedServices());

        if (0 === \count($agentNames)) {
            $io->error('No agents are configured.');

            return Command::FAILURE;
        }

        $agentArg = $input->getArgument('agent');
        if (\is_string($agentArg) && '' !== $agentArg && !$this->agents->has($agentArg)) {
            $io->error(\sprintf('Agent "%s" not found. Available agents: "%s"', $agentArg, implode(', ', $agentNames)));

            return Command::FAILURE;
        }

        // -v (or higher) opens a debug pane that logs tool calls and token usage.
        $debug = $output->isVerbose();
        if (\is_string($agentArg) && '' !== $agentArg) {
            $this->runChat($agentArg, $debug);

            return Command::SUCCESS;
        }

        while (true) {
            $selected = $this->selectAgent($agentNames);

            if (null === $selected) {
                return Command::SUCCESS;
            }

            $this->runChat($selected, $debug);
        }
    }

    /**
     * @param list<string> $agentNames
     */
    private function selectAgent(array $agentNames): ?string
    {
        $tui = new Tui($this->buildStyleSheet());
        $selected = null;
        $models = $this->agentModels();
        $items = array_map(
            static fn (string $name): array => [
                'value' => $name,
                'label' => $name,
                'description' => $models[$name] ?? '',
            ],
            $agentNames,
        );

        // Left: the agent list. Right: live details for the highlighted agent.
        $listPane = new ContainerWidget();
        $listPane->addStyleClass('transcript');
        $listPane->add(new TextWidget('Select an agent:'));
        $list = new SelectListWidget($items, \count($items));
        $listPane->add($list);

        $detail = new MarkdownWidget($this->agentDetailMarkdown($agentNames[0]));
        $detailPane = new ContainerWidget();
        $detailPane->addStyleClass('detail');
        $detailPane->expandVertically(true);
        $detailPane->add($detail);

        $row = new ContainerWidget();
        $row->setStyle(new Style(direction: Direction::Horizontal, gap: 1));
        $row->expandVertically(true);
        $row->add($listPane)->add($detailPane);

        $hint = new TextWidget('↑/↓: browse   ·   Enter: choose   ·   Esc: quit');
        $hint->addStyleClass('hint');

        $tui->add($row)->add($hint);
        $tui->setFocus($list);

        $list->onSelectionChange(function (SelectionChangeEvent $event) use ($tui, $detail): void {
            $detail->setText($this->agentDetailMarkdown($event->getValue()));
            $tui->requestRender();
            $tui->processRender();
        });
        $list->onSelect(static function (SelectEvent $event) use ($tui, &$selected): void {
            $selected = $event->getValue();
            $tui->stop();
        });
        $list->onCancel(static fn () => $tui->stop());

        $tui->run();

        return $selected;
    }

    private function runChat(string $agentName, bool $debug): void
    {
        $tui = new Tui($this->buildStyleSheet());
        $agent = $this->agents->get($agentName);
        $messages = new MessageBag();
        $transcript = \sprintf("# Chat with **%s**\n\n_Ask anything. Streaming replies render as they arrive._\n\n", $agentName);
        $markdown = new MarkdownWidget($transcript);
        $prompt = new InputWidget();
        $prompt->setPrompt('You: ');

        // Debug pane state (only mounted when $debug is true).
        $debugWidget = new TextWidget('Waiting for the first message...');
        $debugLines = [];
        $logDebug = static function (string $line) use ($tui, $debugWidget, &$debugLines): void {
            $debugLines[] = $line;
            $debugLines = \array_slice($debugLines, -14);
            $debugWidget->setText(implode("\n", $debugLines));
            $tui->requestRender();
            $tui->processRender();
        };

        // Bordered, expanding transcript pane with the input docked beneath it.
        $pane = new ContainerWidget();
        $pane->addStyleClass('transcript');
        $pane->expandVertically(true);
        $pane->add($markdown);

        $status = new TextWidget('Ready.');
        $status->addStyleClass('hint');
        $hint = new TextWidget(
            $debug
                ? 'Enter: send   ·   exit / quit / Esc: main menu   ·   debug pane: ON (-v)'
                : 'Enter: send   ·   exit / quit / Esc: main menu   ·   re-run with -v for a debug pane'
        );
        $hint->addStyleClass('hint');

        $tui->add($pane);

        if ($debug) {
            $debugPane = new ContainerWidget();
            $debugPane->addStyleClass('debug');
            $debugPane->add(new TextWidget('agent debug - tool calls & token usage'));
            $debugPane->add($debugWidget);
            $tui->add($debugPane);
        }

        $tui->add($prompt)->add($status)->add($hint);
        $tui->setFocus($prompt);

        $prompt->onCancel(static fn () => $tui->stop());

        $prompt->onSubmit(function (SubmitEvent $event) use ($tui, $agent, $agentName, $prompt, $status, $markdown, $messages, $debug, $logDebug, &$transcript): void {
            $question = trim($event->getValue());
            $prompt->setValue('');

            if ('' === $question) {
                return;
            }

            if (\in_array(strtolower($question), ['exit', 'quit', 'menu', 'back'], true)) {
                $tui->stop();

                return;
            }

            $status->setText(\sprintf('%s: -- thinking --', $agentName));
            $tui->requestRender();
            $tui->processRender();

            $messages->add(Message::ofUser($question));
            $transcript .= \sprintf("**You:** %s\n\n**%s:** ", $question, $agentName);
            $this->flush($tui, $markdown, $transcript);

            if ($debug) {
                $logDebug(\sprintf('> %s <- %s', $agentName, $this->trim($question)));
            }

            try {
                $result = $agent->call($messages, ['stream' => true]);

                if ($result instanceof StreamResult) {
                    $answer = '';
                    $thinkingLogged = false;
                    if ($debug) {
                        $logDebug('  stream opened');
                    }
                    foreach ($result->getContent() as $delta) {
                        if ($delta instanceof TextDelta) {
                            if ('' === $answer) {
                                $status->setText(\sprintf('%s: streaming...', $agentName));
                                if ($debug) {
                                    $logDebug('  first token received');
                                }
                            }
                            $answer .= (string) $delta;
                            $this->flush($tui, $markdown, $transcript.$answer);
                        } elseif ($debug && $delta instanceof ToolCallStart) {
                            $logDebug('  tool start: '.$delta->getName());
                        } elseif ($debug && !$thinkingLogged && $delta instanceof ThinkingStart) {
                            $thinkingLogged = true;
                            $logDebug('  model thinking');
                        } elseif ($debug) {
                            $logDebug('  delta: '.(new \ReflectionClass($delta))->getShortName());
                        }
                    }
                    if ($debug) {
                        $logDebug(\sprintf('  stream closed, answer chars: %d', mb_strlen($answer)));
                    }
                } elseif ($result instanceof TextResult) {
                    $answer = $result->getContent();
                    if ($debug) {
                        $logDebug(\sprintf('  text result chars: %d', mb_strlen($answer)));
                    }
                } else {
                    $answer = '_(unexpected response type from agent)_';
                    if ($debug) {
                        $logDebug('  unexpected result: '.(new \ReflectionClass($result))->getShortName());
                    }
                }

                $messages->add(Message::ofAssistant($answer));
                $transcript .= $answer."\n\n";

                if ($debug && null !== ($usage = $this->tokenUsageLine($result))) {
                    $logDebug($usage);
                }

                $status->setText('Ready.');
            } catch (\Throwable $e) {
                $status->setText('Error. Ready.');
                $transcript .= \sprintf("\n> Error: %s\n\n", $e->getMessage());
                if ($debug) {
                    $logDebug('  exception: '.(new \ReflectionClass($e))->getShortName());
                    $logDebug('  message: '.$this->trim($e->getMessage(), 160));
                    $logDebug(\sprintf('  at %s:%d', basename($e->getFile()), $e->getLine()));
                }
            }

            $this->flush($tui, $markdown, $transcript);
        });

        $tui->run();
    }

    private function buildStyleSheet(): StyleSheet
    {
        $styles = new StyleSheet();
        $styles->addRule('.transcript', new Style(padding: Padding::all(1), border: Border::all(1, 'rounded', 'cyan')));
        $styles->addRule('.debug', new Style(padding: Padding::all(1), border: Border::all(1, 'rounded', 'gray')));
        $styles->addRule('.detail', new Style(padding: Padding::all(1), border: Border::all(1, 'rounded', 'gray')));
        $styles->addRule('.hint', new Style(color: 'gray', dim: true));

        return $styles;
    }

    /**
     * Model name per agent, for the list's short description column.
     *
     * NOTE: this (and {@see agentDetailMarkdown}) uses reflection as a stopgap.
     * AgentInterface exposes getName() but not the model, and
     * SystemPromptInputProcessor keeps its prompt in a private readonly property
     * with no getter — so there is no public way to read what the picker (and
     * the bundle's own `ai:chat`) wants to show. See symfony/ai#2154.
     *
     * @return array<string, string>
     */
    private function agentModels(): array
    {
        $out = [];
        foreach (array_keys($this->agents->getProvidedServices()) as $name) {
            try {
                $agent = $this->unwrapAgent($this->agents->get($name));
                $out[$name] = method_exists($agent, 'getModel') ? (string) $agent->getModel() : '';
            } catch (\Throwable) {
                $out[$name] = '';
            }
        }

        return $out;
    }

    /**
     * Full details for the highlighted agent: model, model capabilities, and the
     * complete system prompt. (Temperature isn't configured per-agent here, and
     * the platform's Model carries no pricing, so neither is shown.).
     */
    private function agentDetailMarkdown(string $name): string
    {
        try {
            $agent = $this->unwrapAgent($this->agents->get($name));

            $model = method_exists($agent, 'getModel') ? (string) $agent->getModel() : '';
            $capabilities = $this->modelCapabilities($agent, $model);
            $prompt = $this->systemPromptText($agent);

            $md = "## {$name}\n\n";
            $md .= '' !== $model ? "**Model:** `{$model}`\n\n" : "_Multi-agent / no single model._\n\n";
            if ('' !== $capabilities) {
                $md .= "**Accepts:** {$capabilities}\n\n";
            }

            $tools = $this->agentTools($agent);
            if ([] !== $tools) {
                $md .= "**Tools:**\n\n";
                foreach ($tools as $tool) {
                    $md .= \sprintf("- `%s`%s\n", $tool['name'], '' !== $tool['description'] ? ' — '.$this->trim($tool['description'], 64) : '');
                }
                $md .= "\n";
            }

            $md .= "**System prompt:**\n\n";
            $md .= '' !== $prompt ? $this->trim($prompt, 600) : '_none configured_';

            return $md;
        } catch (\Throwable) {
            return "## {$name}\n\n_No details available._";
        }
    }

    /**
     * The agent's tools (name + description), read from the tool-calling
     * AgentProcessor's toolbox. Empty for agents configured with `tools: false`.
     *
     * @return list<array{name: string, description: string}>
     */
    private function agentTools(object $agent): array
    {
        try {
            $processors = $this->readProperty($agent, 'inputProcessors');
            if (!is_iterable($processors)) {
                return [];
            }

            foreach ($processors as $processor) {
                if (!$processor instanceof AgentProcessor) {
                    continue;
                }

                $toolbox = $this->readProperty($processor, 'toolbox');
                if (!$toolbox instanceof ToolboxInterface) {
                    return [];
                }

                $tools = [];
                foreach ($toolbox->getTools() as $tool) {
                    $tools[] = ['name' => $tool->getName(), 'description' => $tool->getDescription()];
                }

                return $tools;
            }
        } catch (\Throwable) {
        }

        return [];
    }

    /**
     * Comma-separated model capabilities (tool-calling, vision, …), resolved via
     * the agent's platform model catalog. Empty if unavailable.
     */
    private function modelCapabilities(object $agent, string $model): string
    {
        if ('' === $model) {
            return '';
        }

        try {
            $platform = $this->readProperty($agent, 'platform');
            if (!$platform instanceof PlatformInterface) {
                return '';
            }

            $capabilities = $platform->getModelCatalog()->getModel($model)->getCapabilities();
            $names = array_map(static fn (object $c): string => strtolower(str_replace('_', '-', $c->name)), $capabilities);

            return implode(', ', $names);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Peel decorators (e.g. TraceableAgent) until we reach the concrete Agent.
     */
    private function unwrapAgent(object $agent): object
    {
        for ($i = 0; $i < 5 && !$agent instanceof Agent; ++$i) {
            $inner = $this->readProperty($agent, 'agent');
            if (!\is_object($inner)) {
                break;
            }
            $agent = $inner;
        }

        return $agent;
    }

    private function systemPromptText(object $agent): string
    {
        $prompt = $this->systemPromptOf($agent);

        if (\is_string($prompt)) {
            return trim($prompt);
        }
        if ($prompt instanceof \Stringable) {
            return trim((string) $prompt);
        }

        return '';
    }

    private function systemPromptOf(object $agent): mixed
    {
        $processors = $this->readProperty($agent, 'inputProcessors');
        if (!is_iterable($processors)) {
            return null;
        }

        foreach ($processors as $processor) {
            if ($processor instanceof SystemPromptInputProcessor) {
                return $this->readProperty($processor, 'systemPrompt');
            }
        }

        return null;
    }

    private function readProperty(object $object, string $property): mixed
    {
        for ($ref = new \ReflectionObject($object); $ref; $ref = $ref->getParentClass()) {
            if ($ref->hasProperty($property)) {
                $p = $ref->getProperty($property);
                $p->setAccessible(true);

                return $p->getValue($object);
            }
        }

        return null;
    }

    /**
     * Format the token usage attached to a result's metadata (set by the
     * platform during/after the call), or null if none is available.
     */
    private function tokenUsageLine(object $result): ?string
    {
        if (!method_exists($result, 'getMetadata')) {
            return null;
        }

        $metadata = $result->getMetadata();
        $usage = $metadata->get('token_usage');
        if (!$usage instanceof TokenUsage) {
            return '  tokens: unavailable';
        }

        $parts = [];
        if (null !== $usage->getPromptTokens()) {
            $parts[] = 'in '.$usage->getPromptTokens();
        }
        if (null !== $usage->getCompletionTokens()) {
            $parts[] = 'out '.$usage->getCompletionTokens();
        }
        if (null !== $usage->getTotalTokens()) {
            $parts[] = 'total '.$usage->getTotalTokens();
        }
        if (null !== $usage->getRemainingTokensMinute()) {
            $parts[] = 'rem/min '.$usage->getRemainingTokensMinute();
        }

        return $parts ? '  tokens: '.implode('  ', $parts) : '  tokens: unavailable';
    }

    private function trim(string $text, int $max = 48): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? $text;

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }

    /**
     * Push the latest transcript into the markdown pane and flush it to the
     * terminal immediately, so streamed deltas appear as they arrive even
     * while the submit handler is blocking the event loop.
     */
    private function flush(Tui $tui, MarkdownWidget $markdown, string $text): void
    {
        $markdown->setText($text);
        $tui->requestRender();
        $tui->processRender();
    }
}

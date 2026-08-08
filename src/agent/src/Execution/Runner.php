<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\StreamListener;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Drives a single agent invocation: it exposes the agent's tools to the model, invokes the model and
 * runs the tool-calling loop until the model returns a final result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class Runner
{
    /**
     * Sources get collected during tool calls on class level to be able to handle consecutive tool calls.
     * They get added to the result metadata and reset when the outermost agent call is finished via nesting level.
     */
    private SourceCollection $sources;

    /**
     * Tracks the nesting level of agent calls.
     */
    private int $nestingLevel = 0;

    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly ?ToolboxInterface $toolbox = null,
        private readonly ?ToolExecutorInterface $toolExecutor = null,
        private readonly ?int $maxToolCalls = 50,
        private readonly bool $excludeToolMessages = false,
        private readonly bool $includeSources = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
    ) {
        $this->sources = new SourceCollection();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function run(AgentInterface $agent, string $model, MessageBag $messages, array $options): ResultInterface
    {
        $options = $this->exposeTools($options);

        $result = $this->platform->invoke($model, $messages, $options)->getResult();

        if (null === $this->toolExecutor) {
            return $result;
        }

        if ($result instanceof StreamResult) {
            $result->addListener(new StreamListener(
                fn (ToolCallResult $toolCallResult, ?AssistantMessage $assistantResponse = null): ResultInterface => $this->handleToolCalls($agent, $messages, $options, $toolCallResult, $assistantResponse),
            ));

            return $result;
        }

        $toolCallResult = $result instanceof MultiPartResult ? $result->asToolCallResult() : $result;

        if (!$toolCallResult instanceof ToolCallResult) {
            return $result;
        }

        // Pass the full mixed result so thinking and text survive the tool round-trip
        return $this->handleToolCalls($agent, $messages, $options, $toolCallResult, Message::ofAssistant($result));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function exposeTools(array $options): array
    {
        if (!$this->toolbox instanceof ToolboxInterface) {
            return $options;
        }

        $toolMap = $this->toolbox->getTools();
        if ([] === $toolMap) {
            return $options;
        }

        // only filter tool map if a list of strings is provided as option
        if (isset($options['tools']) && \is_array($options['tools']) && $this->isFlatStringArray($options['tools'])) {
            $toolMap = array_values(array_filter($toolMap, static fn (Tool $tool): bool => \in_array($tool->getName(), $options['tools'], true)));
        }

        $options['tools'] = $toolMap;

        return $options;
    }

    /**
     * @param array<mixed> $tools
     */
    private function isFlatStringArray(array $tools): bool
    {
        return array_reduce($tools, static fn (bool $carry, mixed $item): bool => $carry && \is_string($item), true);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function handleToolCalls(AgentInterface $agent, MessageBag $messages, array $options, ToolCallResult $result, ?AssistantMessage $assistantResponse = null): ResultInterface
    {
        \assert($this->toolExecutor instanceof ToolExecutorInterface);

        ++$this->nestingLevel;
        $messages = $this->excludeToolMessages ? clone $messages : $messages;

        try {
            $iterations = 0;
            do {
                if (null !== $this->maxToolCalls && ++$iterations > $this->maxToolCalls) {
                    throw new MaxIterationsExceededException($this->maxToolCalls);
                }

                $toolCalls = array_values($result->getContent());
                $toolResults = $this->toolExecutor->execute($toolCalls);

                // Preserve the provider's original content on the first iteration; later
                // tool-only iterations synthesize the assistant tool-call message as before
                if (null === $assistantResponse) {
                    $assistantResponse = Message::ofAssistant(...$toolCalls);
                } elseif (!$assistantResponse->hasToolCalls()) {
                    $assistantResponse = new AssistantMessage(...$assistantResponse->getContent(), ...$toolCalls);
                }
                $messages->add($assistantResponse);
                $assistantResponse = null;

                foreach ($toolResults as $i => $toolResult) {
                    $messages->add(Message::ofToolCall($toolCalls[$i], $this->resultConverter->convert($toolResult)));
                }

                if ($this->includeSources) {
                    foreach ($toolResults as $toolResult) {
                        if (null !== $toolResult->getSources()) {
                            $this->sources = $this->sources->merge($toolResult->getSources());
                        }
                    }
                }

                $event = new ToolCallsExecuted($toolResults);
                $this->eventDispatcher?->dispatch($event);

                $result = $event->hasResult() ? $event->getResult() : $agent->call($messages, $options);
            } while ($result instanceof ToolCallResult);
        } finally {
            --$this->nestingLevel;

            if ($this->includeSources && 0 === $this->nestingLevel && $result instanceof ToolCallResult) {
                $this->sources = new SourceCollection();
            }
        }

        if ($this->includeSources && 0 === $this->nestingLevel) {
            $result->getMetadata()->add('sources', $this->sources);
            $this->sources = new SourceCollection();
        }

        return $result;
    }
}

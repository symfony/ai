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
use Symfony\AI\Agent\Approval\ApprovalDecision;
use Symfony\AI\Agent\Approval\ApprovalManagerInterface;
use Symfony\AI\Agent\Approval\ApprovalPendingResult;
use Symfony\AI\Agent\Approval\Checkpoint\ExecutionCheckpoint;
use Symfony\AI\Agent\Approval\Event\ToolApprovalRequestedEvent;
use Symfony\AI\Agent\Approval\Event\ToolApprovalResolvedEvent;
use Symfony\AI\Agent\Approval\Exception\CheckpointExpiredException;
use Symfony\AI\Agent\Approval\Exception\InvalidCheckpointException;
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Drives a single agent invocation, producing the generator of updates an {@see Execution} wraps.
 *
 * The tool-calling loop is iterative: every round invokes the model, executes the tool calls it requested
 * and feeds the results back, until the model answers without asking for further tools. Streamed rounds
 * are consumed here as well, so a streamed tool call is just another round of that same loop.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Saiful Islam <saif012@gmail.com>
 *
 * @internal
 */
final class Runner
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly ?ToolboxInterface $toolbox = null,
        private readonly ?ToolExecutorInterface $toolExecutor = null,
        private readonly ?int $maxToolCalls = 50,
        private readonly bool $excludeToolMessages = false,
        private readonly bool $includeSources = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
        private readonly ?ApprovalManagerInterface $approvalManager = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    public function run(string $model, MessageBag $messages, array $options): \Generator
    {
        $options = $this->exposeTools($options);
        $messages = $this->excludeToolMessages ? clone $messages : $messages;

        $sources = new SourceCollection();
        $metadata = new Metadata();
        $iterations = 0;

        while (true) {
            yield new Progress('model_request', 'Invoking model.', $model);

            $result = $this->platform->invoke($model, $messages, $options)->getResult();

            $assistantMessage = null;
            if ($result instanceof StreamResult) {
                [$result, $assistantMessage] = yield from $this->consumeStream($result);
            }

            $toolCallResult = $this->extractToolCallResult($result);
            if (null === $toolCallResult || null === $this->toolExecutor) {
                break;
            }

            // $metadata aggregates the tool calling rounds, the final result carries its own
            $metadata->merge($result->getMetadata());

            if (null !== $this->maxToolCalls && ++$iterations > $this->maxToolCalls) {
                throw new MaxIterationsExceededException($this->maxToolCalls);
            }

            $toolCalls = array_values($toolCallResult->getContent());

            if (null !== $this->approvalManager && null !== $this->toolbox) {
                $toolDefinitions = $this->toolbox->getTools();
                $toolMap = [];
                foreach ($toolDefinitions as $def) {
                    $toolMap[$def->getName()] = $def;
                }

                $toolResults = [];
                foreach ($toolCalls as $index => $toolCall) {
                    $toolDef = $toolMap[$toolCall->getName()] ?? null;
                    if (null !== $toolDef && $this->approvalManager->requiresApproval($toolDef, $toolCall)) {
                        $checkpoint = new ExecutionCheckpoint(
                            id: (string) Uuid::v7(),
                            agentName: $options['agent_name'] ?? 'agent',
                            model: $model,
                            messages: $messages,
                            options: $options,
                            pendingToolCalls: array_values(\array_slice($toolCalls, $index)),
                            completedToolResults: $toolResults,
                            iterations: $iterations,
                            sources: $this->includeSources ? $sources : null,
                        );

                        $prompt = $this->approvalManager->formatPrompt($toolDef, $toolCall);
                        $roles = $this->approvalManager->getApprovalRequirement($toolDef)->roles ?? [];

                        $approvalEvent = new ToolApprovalRequestedEvent($checkpoint, $toolCall, $toolDef, null, $prompt, $roles);
                        $this->eventDispatcher?->dispatch($approvalEvent);

                        if ($approvalEvent->hasDecision()) {
                            $decision = $approvalEvent->getDecision();
                            $toolResults[] = $this->executeDecision($toolCall, $decision);
                            continue;
                        }

                        $this->approvalManager->getCheckpointStore()?->save($checkpoint);
                        $token = $this->approvalManager->getSigner()?->encode($checkpoint);

                        $pendingResult = new ApprovalPendingResult($checkpoint, $toolCall, $toolDef, $token, $prompt, $roles);
                        yield new ResultUpdate($pendingResult);

                        return;
                    }

                    $results = yield from $this->toolExecutor->execute([$toolCall]);
                    foreach ($results as $res) {
                        $toolResults[] = $res;
                    }
                }
            } else {
                $toolResults = yield from $this->toolExecutor->execute($toolCalls);
            }

            $messages->add($assistantMessage ?? Message::ofAssistant($result));
            foreach ($toolResults as $i => $toolResult) {
                $messages->add(Message::ofToolCall($toolCalls[$i], $this->resultConverter->convert($toolResult)));

                if (null !== $toolResult->getSources()) {
                    $sources = $sources->merge($toolResult->getSources());
                }
            }

            $event = new ToolCallsExecuted($toolResults);
            $this->eventDispatcher?->dispatch($event);

            if ($event->hasResult()) {
                $result = $event->getResult();

                break;
            }
        }

        $result->getMetadata()->merge($metadata);

        if ($this->includeSources) {
            $result->getMetadata()->add('sources', $sources);
        }

        yield new ResultUpdate($result);
    }

    /**
     * Resumes execution of a previously suspended agent call after receiving an approval decision.
     */
    public function resume(
        AgentInterface $agent,
        ExecutionCheckpoint|string $checkpoint,
        ApprovalDecision $decision,
    ): ResultInterface {
        if (\is_string($checkpoint)) {
            $checkpoint = $this->resolveCheckpoint($checkpoint);
        }

        if ($checkpoint->isExpired()) {
            throw CheckpointExpiredException::forCheckpoint($checkpoint->getId());
        }

        $pendingToolCalls = $checkpoint->getPendingToolCalls();
        if ([] === $pendingToolCalls) {
            return $agent->call($checkpoint->getMessages(), $checkpoint->getOptions())->getResult();
        }

        $currentToolCall = array_shift($pendingToolCalls);
        $toolDef = null;
        if (null !== $this->toolbox) {
            foreach ($this->toolbox->getTools() as $def) {
                if ($def->getName() === $currentToolCall->getName()) {
                    $toolDef = $def;
                    break;
                }
            }
        }

        if (null !== $toolDef) {
            $this->eventDispatcher?->dispatch(new ToolApprovalResolvedEvent($checkpoint, $currentToolCall, $toolDef, $agent, $decision));
        }

        $toolResult = $this->executeDecision($currentToolCall, $decision);
        $toolResults = [...$checkpoint->getCompletedToolResults(), $toolResult];

        $messages = $checkpoint->getMessages();
        $options = $checkpoint->getOptions();

        // Check if more pending tool calls exist in this turn
        if ([] !== $pendingToolCalls && null !== $this->approvalManager && null !== $this->toolbox) {
            $toolDefinitions = $this->toolbox->getTools();
            $toolMap = [];
            foreach ($toolDefinitions as $def) {
                $toolMap[$def->getName()] = $def;
            }

            foreach ($pendingToolCalls as $index => $toolCall) {
                $pendingToolDef = $toolMap[$toolCall->getName()] ?? null;
                if (null !== $pendingToolDef && $this->approvalManager->requiresApproval($pendingToolDef, $toolCall, $agent)) {
                    $nextCheckpoint = new ExecutionCheckpoint(
                        id: (string) Uuid::v7(),
                        agentName: $checkpoint->getAgentName(),
                        model: $checkpoint->getModel(),
                        messages: $messages,
                        options: $options,
                        pendingToolCalls: array_values(\array_slice($pendingToolCalls, $index)),
                        completedToolResults: $toolResults,
                        iterations: $checkpoint->getIterations(),
                        sources: $checkpoint->getSources(),
                    );

                    $prompt = $this->approvalManager->formatPrompt($pendingToolDef, $toolCall);
                    $roles = $this->approvalManager->getApprovalRequirement($pendingToolDef)->roles ?? [];

                    $approvalEvent = new ToolApprovalRequestedEvent($nextCheckpoint, $toolCall, $pendingToolDef, $agent, $prompt, $roles);
                    $this->eventDispatcher?->dispatch($approvalEvent);

                    if ($approvalEvent->hasDecision()) {
                        $nextDecision = $approvalEvent->getDecision();
                        $toolResults[] = $this->executeDecision($toolCall, $nextDecision);
                        continue;
                    }

                    $this->approvalManager->getCheckpointStore()?->save($nextCheckpoint);
                    $this->approvalManager->getCheckpointStore()?->remove($checkpoint->getId());

                    $token = $this->approvalManager->getSigner()?->encode($nextCheckpoint);

                    return new ApprovalPendingResult($nextCheckpoint, $toolCall, $pendingToolDef, $token, $prompt, $roles);
                }

                $toolResults[] = $this->toolbox->execute($toolCall);
            }
        }

        // All tool calls for this turn are resolved
        $allToolCalls = [];
        foreach ($toolResults as $res) {
            $allToolCalls[] = $res->getToolCall();
        }

        $messages->add(Message::ofAssistant(...$allToolCalls));
        foreach ($toolResults as $res) {
            $messages->add(Message::ofToolCall($res->getToolCall(), $this->resultConverter->convert($res)));
        }

        $this->approvalManager?->getCheckpointStore()?->remove($checkpoint->getId());

        $event = new ToolCallsExecuted($toolResults);
        $this->eventDispatcher?->dispatch($event);

        return $event->hasResult() ? $event->getResult() : $agent->call($messages, $options)->getResult();
    }

    /**
     * Consumes a streamed round, forwarding every delta as a progress update.
     *
     * The stream is drained completely even after a tool call was seen, since its metadata (e.g. token
     * usage) is only complete once the underlying generator is exhausted.
     *
     * @return \Generator<int, UpdateInterface, mixed, array{ResultInterface, AssistantMessage}>
     */
    private function consumeStream(StreamResult $stream): \Generator
    {
        $text = '';
        $toolCalls = [];

        foreach ($stream->getContent() as $delta) {
            if ($delta instanceof ToolCallComplete) {
                $toolCalls = [...$toolCalls, ...$delta->getToolCalls()];

                continue;
            }

            if ([] !== $toolCalls) {
                // the model asked for tools, the remaining deltas of this round are not part of the answer
                continue;
            }

            if ($delta instanceof TextDelta) {
                $text .= $delta->getText();
            }

            yield new Progress('delta', 'Received a streamed delta.', $delta);
        }

        $turn = $stream->getAssistantMessage();

        if ([] !== $toolCalls) {
            $result = new ToolCallResult($toolCalls);
        } else {
            // a streamed structured output round ends with the object assembled by the platform's listener
            $result = $this->streamedObjectResult($stream) ?? $this->turnResult($turn, $text);
        }

        $result->getMetadata()->merge($stream->getMetadata());

        return [$result, $turn];
    }

    /**
     * The streamed turn as the result the same round would have returned unstreamed, so a thinking
     * block and its signature survive into the next turn.
     */
    private function turnResult(AssistantMessage $turn, string $text): ResultInterface
    {
        $parts = [];
        foreach ($turn->getContent() as $content) {
            if ($content instanceof Thinking) {
                $parts[] = new ThinkingResult($content->getContent(), $content->getSignature());
            }

            if ($content instanceof Text) {
                $parts[] = new TextResult($content->getText(), $content->getSignature());
            }
        }

        if ([] === $parts) {
            return new TextResult($text);
        }

        if (1 === \count($parts) && $parts[0] instanceof TextResult) {
            return $parts[0];
        }

        return new MultiPartResult($parts);
    }

    private function streamedObjectResult(StreamResult $stream): ?ObjectResult
    {
        foreach ($stream->getListeners() as $listener) {
            if ($listener instanceof PartialObjectStreamListener) {
                return $listener->getFinalObjectResult();
            }
        }

        return null;
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

    private function extractToolCallResult(ResultInterface $result): ?ToolCallResult
    {
        if ($result instanceof ToolCallResult) {
            return $result;
        }

        if ($result instanceof MultiPartResult) {
            return $result->asToolCallResult();
        }

        return null;
    }

    private function executeDecision(ToolCall $toolCall, ApprovalDecision $decision): ToolResult
    {
        if ($decision->isRejected()) {
            $feedback = $decision->getFeedback() ?? 'Tool execution was denied by human reviewer.';

            return new ToolResult($toolCall, $feedback);
        }

        if ($decision->isModified() && null !== $decision->getModifiedArguments()) {
            $toolCall = new ToolCall($toolCall->getId(), $toolCall->getName(), $decision->getModifiedArguments());
        }

        if (null === $this->toolbox) {
            throw new LogicException('Cannot execute tool call without a configured toolbox.');
        }

        return $this->toolbox->execute($toolCall);
    }

    private function resolveCheckpoint(string $checkpoint): ExecutionCheckpoint
    {
        if (null !== $this->approvalManager?->getSigner()) {
            try {
                return $this->approvalManager->getSigner()->decode($checkpoint);
            } catch (\Throwable) {
                // Fallback to store lookup if token decoding failed
            }
        }

        if (null !== $this->approvalManager?->getCheckpointStore()) {
            $found = $this->approvalManager->getCheckpointStore()->get($checkpoint);
            if (null !== $found) {
                return $found;
            }
        }

        throw InvalidCheckpointException::unreadable(\sprintf('Checkpoint "%s" could not be resolved from token or store.', $checkpoint));
    }
}

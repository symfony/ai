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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Commentary;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\CommentaryResult;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Drives a single agent invocation, producing the generator of updates an {@see Execution} wraps.
 *
 * The tool-calling loop is iterative: every round invokes the model, executes the tool calls it requested
 * and feeds the results back, until the model answers without asking for further tools. Streamed rounds
 * are consumed here as well, so a streamed tool call is just another round of that same loop.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
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
        private readonly bool $includeSources = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    public function run(string $model, MessageBag $messages, array $options, ?Cancellation $cancellation = null): \Generator
    {
        [$options, $maxRounds, $deferToolCalls] = $this->extractStepOptions($options);
        [$options, $allowedTools] = $this->exposeTools($options);

        $sources = new SourceCollection();
        $metadata = new Metadata();
        $iterations = 0;
        $rounds = 0;
        $round = 0;
        $firstRound = $this->firstRound($messages);
        $stopped = false;

        while (true) {
            $deferredResult = $this->platform->invoke($model, $messages, $options);
            $cancellation?->activate($deferredResult->getRawResult());

            try {
                yield new Progress(Progress::STAGE_MODEL_REQUEST, 'Invoking model.', $model);

                if ($cancellation?->isRequested()) {
                    return;
                }

                $result = $deferredResult->getResult();

                $assistantMessage = null;
                if ($result instanceof StreamResult) {
                    $streamedResult = yield from $this->consumeStream($result, $cancellation);
                    if (null === $streamedResult) {
                        return;
                    }

                    [$result, $assistantMessage] = $streamedResult;
                }
            } finally {
                $cancellation?->deactivate();
            }

            if ($cancellation?->isRequested()) {
                return;
            }

            $round = $firstRound + $rounds;
            ++$rounds;

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
            $this->denyRestrictedToolCalls($toolCalls, $allowedTools);

            $toolRoundMetadata = [...$result->getMetadata()->all(), 'round' => $round];

            $deferredCalls = [];
            $executableCalls = [];
            foreach ($toolCalls as $i => $toolCall) {
                if (null !== $deferToolCalls && $deferToolCalls($toolCall)) {
                    $deferredCalls[$i] = true;

                    continue;
                }

                $executableCalls[] = $toolCall;
            }
            $roundIsDeferred = [] !== $deferredCalls;

            $toolResults = [] === $executableCalls ? [] : yield from $this->toolExecutor->execute($executableCalls);

            $messages = $messages->with($this->toAssistantMessage($result, $assistantMessage, $toolRoundMetadata));
            $executed = 0;
            foreach ($toolCalls as $i => $toolCall) {
                if (isset($deferredCalls[$i])) {
                    continue;
                }

                $toolResult = $toolResults[$executed++];
                $messages = $messages->with(Message::ofToolCall($toolCall, $this->resultConverter->convert($toolResult)));

                if (null !== $toolResult->getSources()) {
                    $sources = $sources->merge($toolResult->getSources());
                }
            }

            if ($roundIsDeferred && [] === $toolResults) {
                $stopped = true;

                break;
            }

            $event = new ToolCallsExecuted($toolResults);
            $this->eventDispatcher?->dispatch($event);

            if ($roundIsDeferred) {
                // the deferred calls have no answer yet, so a listener cannot replace it
                $stopped = true;

                break;
            }

            if ($event->hasResult()) {
                $result = $event->getResult();
                $assistantMessage = null;
                ++$round;

                break;
            }

            if (null !== $maxRounds && $rounds >= $maxRounds) {
                $stopped = true;

                break;
            }
        }

        $roundMetadata = [...$result->getMetadata()->all(), 'round' => $round];
        if ($this->includeSources) {
            $roundMetadata['sources'] = $sources;
        }
        $answer = fn (?ResultInterface $replacement = null): MessageBag => $stopped
            ? $messages
            : $messages->with(null === $replacement
                ? $this->toAssistantMessage($result, $assistantMessage, $roundMetadata)
                : $this->toAssistantMessage($replacement, null, $roundMetadata));

        // Merged in order, so mergeable values like the token usage add up and the final result's own values win
        $metadata->merge($result->getMetadata());
        $result->getMetadata()->set($metadata->all());

        if ($this->includeSources) {
            $result->getMetadata()->add('sources', $sources);
        }

        yield new ResultUpdate($result, $answer);
    }

    /**
     * Continues after the highest round already on the messages, so a bag that holds one assistant message for several rounds keeps counting.
     */
    private function firstRound(MessageBag $messages): int
    {
        $assistantMessages = 0;
        $highest = 0;
        foreach ($messages->getMessages() as $message) {
            if (!$message instanceof AssistantMessage) {
                continue;
            }

            ++$assistantMessages;
            $round = $message->getMetadata()->get('round');
            if (\is_int($round) && $round > $highest) {
                $highest = $round;
            }
        }

        return 1 + max($assistantMessages, $highest);
    }

    /**
     * The round's answer as assistant message, carrying the metadata of that round only, like its token usage.
     *
     * @param array<string, mixed> $metadata
     */
    private function toAssistantMessage(ResultInterface $result, ?AssistantMessage $streamedMessage, array $metadata): AssistantMessage
    {
        $message = $streamedMessage ?? Message::ofAssistant($result);
        $message->getMetadata()->set($metadata);

        return $message;
    }

    /**
     * Consumes a streamed round, forwarding every delta as a progress update.
     *
     * The stream is drained completely even after a tool call was seen, since its metadata (e.g. token
     * usage) is only complete once the underlying generator is exhausted.
     *
     * @return \Generator<int, UpdateInterface, mixed, array{ResultInterface, AssistantMessage}|null>
     */
    private function consumeStream(StreamResult $stream, ?Cancellation $cancellation): \Generator
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

            yield new Progress(Progress::STAGE_DELTA, 'Received a streamed delta.', $delta);

            if ($cancellation?->isRequested()) {
                return null;
            }
        }

        if ($cancellation?->isRequested()) {
            return null;
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

            if ($content instanceof Commentary) {
                $parts[] = new CommentaryResult($content->getContent());
            }

            if ($content instanceof Text) {
                $parts[] = new TextResult($content->getText(), $content->getSignature(), $content->getPhase());
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
     * Takes the step options out of the options, so they reach neither the platform nor the output processors.
     *
     * @param array<string, mixed> $options
     *
     * @return array{array<string, mixed>, int|null, (\Closure(ToolCall): bool)|null} the remaining options, the maximum of model rounds and the check which tool calls are left to the caller
     */
    private function extractStepOptions(array $options): array
    {
        $maxRounds = $options['max_rounds'] ?? null;
        $deferToolCalls = $options['defer_tool_calls'] ?? false;
        unset($options['max_rounds'], $options['defer_tool_calls']);

        if (null !== $maxRounds && (!\is_int($maxRounds) || $maxRounds < 1)) {
            throw new InvalidArgumentException('The "max_rounds" option must be a positive integer.');
        }

        if (false === $deferToolCalls) {
            return [$options, $maxRounds, null];
        }

        if (true === $deferToolCalls) {
            return [$options, $maxRounds, static fn (ToolCall $toolCall): bool => true];
        }

        if ($deferToolCalls instanceof \Closure) {
            return [$options, $maxRounds, $deferToolCalls];
        }

        if (!\is_array($deferToolCalls) || !array_is_list($deferToolCalls) || [] !== array_filter($deferToolCalls, static fn (mixed $name): bool => !\is_string($name) || '' === $name)) {
            throw new InvalidArgumentException('The "defer_tool_calls" option must be a boolean, a list of tool names or a closure.');
        }

        return [$options, $maxRounds, static fn (ToolCall $toolCall): bool => \in_array($toolCall->getName(), $deferToolCalls, true)];
    }

    /**
     * Exposes the registered tools, narrowed down by the tool names given in the tools option.
     *
     * @param array<string, mixed> $options
     *
     * @return array{array<string, mixed>, list<string>|null} the options and the names of the tools allowed to be executed, null if unrestricted
     */
    private function exposeTools(array $options): array
    {
        $allowedTools = null;
        $serverTools = [];

        if (isset($options['tools']) && \is_array($options['tools'])) {
            $names = array_values(array_filter($options['tools'], static fn (mixed $tool): bool => \is_string($tool)));
            $serverTools = array_values(array_filter($options['tools'], static fn (mixed $tool): bool => \is_array($tool)));

            // only restrict tools if tool names are provided as option, an empty option allows no tool at all
            if ([] !== $names || [] === $options['tools']) {
                $allowedTools = $names;
            }
        }

        if (!$this->toolbox instanceof ToolboxInterface) {
            return [$options, $allowedTools];
        }

        $toolMap = $this->toolbox->getTools();
        if ([] === $toolMap) {
            return [$options, $allowedTools];
        }

        if (null !== $allowedTools) {
            $toolMap = array_values(array_filter($toolMap, static fn (Tool $tool): bool => \in_array($tool->getName(), $allowedTools, true)));
        }

        $options['tools'] = [...$toolMap, ...$serverTools];

        return [$options, $allowedTools];
    }

    /**
     * Rejects calls of registered tools the tools option did not allow, unknown names are left to the toolbox.
     *
     * @param list<ToolCall>    $toolCalls
     * @param list<string>|null $allowedTools
     */
    private function denyRestrictedToolCalls(array $toolCalls, ?array $allowedTools): void
    {
        if (null === $allowedTools || !$this->toolbox instanceof ToolboxInterface) {
            return;
        }

        // listed again, since the tools of a toolbox can change during a run
        $registeredTools = array_map(static fn (Tool $tool): string => $tool->getName(), $this->toolbox->getTools());

        foreach ($toolCalls as $toolCall) {
            if (\in_array($toolCall->getName(), $registeredTools, true) && !\in_array($toolCall->getName(), $allowedTools, true)) {
                throw ToolNotFoundException::notFoundForToolCall($toolCall);
            }
        }
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
}

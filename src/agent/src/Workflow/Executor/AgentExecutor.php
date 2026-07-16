<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Executor;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\DeferrableAgentInterface;
use Symfony\AI\Agent\DeferredAgentCall;
use Symfony\AI\Agent\Exception\WorkflowExecutorException;
use Symfony\AI\Agent\Workflow\AsyncExecutorInterface;
use Symfony\AI\Agent\Workflow\PendingExecution;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Executor that delegates to an AgentInterface.
 *
 * Reads input from the state (configurable key), calls the agent, and writes the result content back
 * to state under a configurable output key. When a metadata key is configured, the result metadata
 * (token usage, etc.) is stored as well.
 *
 * When the agent implements {@see DeferrableAgentInterface}, this executor also implements
 * {@see AsyncExecutorInterface}, so several agent places can run concurrently inside an AND-split.
 *
 * When a history key is configured, the state value at that key is treated as the running
 * conversation: it is prepended to the input and the assistant reply is appended back. Note that any
 * messages the agent's input processors add (e.g. a system prompt) become part of that stored
 * conversation.
 *
 * The history is kept as a live {@see MessageBag}, which is not JSON-serializable, so a configured
 * history key only round-trips through the InMemory store or within a single, non-resumed run;
 * resuming from a Cache/Redis/Filesystem store loses the MessageBag. Keep conversational workflows on
 * the InMemory store until MessageBag persistence is supported by the state stores.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class AgentExecutor implements AsyncExecutorInterface
{
    /**
     * @param non-empty-string      $inputKey    State key holding the input prompt/messages
     * @param non-empty-string      $outputKey   State key to write the agent result content into
     * @param non-empty-string|null $metadataKey State key to write the agent result metadata into, or null to skip
     * @param array<string, mixed>  $options     Options always passed to the agent call
     * @param non-empty-string|null $optionsKey  State key holding an options array merged over $options, or null
     * @param non-empty-string|null $historyKey  State key holding a MessageBag accumulating the conversation, or null
     */
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly string $inputKey = 'input',
        private readonly string $outputKey = 'output',
        private readonly ?string $metadataKey = null,
        private readonly array $options = [],
        private readonly ?string $optionsKey = null,
        private readonly ?string $historyKey = null,
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        $messages = $this->buildMessages($state);

        try {
            $result = $this->agent->call($messages, $this->resolveOptions($state));
        } catch (\Throwable $exception) {
            throw new WorkflowExecutorException(\sprintf('Agent execution failed at place "%s": "%s".', $place, $exception->getMessage()), 0, $exception);
        }

        return $this->writeResult($state, $result, $messages);
    }

    public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
    {
        if (!$this->agent instanceof DeferrableAgentInterface) {
            // The agent cannot be deferred; settle() will run execute() synchronously.
            return new PendingExecution(null);
        }

        $messages = $this->buildMessages($state);

        try {
            $call = $this->agent->prepare($messages, $this->resolveOptions($state));
        } catch (\Throwable $exception) {
            throw new WorkflowExecutorException(\sprintf('Agent execution failed at place "%s": "%s".', $place, $exception->getMessage()), 0, $exception);
        }

        return new PendingExecution($call);
    }

    public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
    {
        if (!$pending->handle instanceof DeferredAgentCall || !$this->agent instanceof DeferrableAgentInterface) {
            return $this->execute($state, $place);
        }

        try {
            $result = $this->agent->finish($pending->handle);
        } catch (\Throwable $exception) {
            throw new WorkflowExecutorException(\sprintf('Agent execution failed at place "%s": "%s".', $place, $exception->getMessage()), 0, $exception);
        }

        return $this->writeResult($state, $result, $pending->handle->messages);
    }

    private function buildMessages(WorkflowStateInterface $state): MessageBag
    {
        $input = $state->get($this->inputKey);

        if ($input instanceof MessageBag) {
            $messages = $input;
        } elseif (\is_string($input)) {
            $messages = new MessageBag(Message::ofUser($input));
        } else {
            throw new WorkflowExecutorException(\sprintf('AgentExecutor expects state key "%s" to contain a string or MessageBag, got "%s".', $this->inputKey, get_debug_type($input)));
        }

        if (null === $this->historyKey) {
            return $messages;
        }

        $history = $state->get($this->historyKey);

        if ($history instanceof MessageBag) {
            return $history->merge($messages);
        }

        if (null !== $history) {
            throw new WorkflowExecutorException(\sprintf('AgentExecutor expects state key "%s" to contain a MessageBag, got "%s".', $this->historyKey, get_debug_type($history)));
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOptions(WorkflowStateInterface $state): array
    {
        if (null === $this->optionsKey) {
            return $this->options;
        }

        $stateOptions = $state->get($this->optionsKey);

        return \is_array($stateOptions) ? array_merge($this->options, $stateOptions) : $this->options;
    }

    private function writeResult(WorkflowStateInterface $state, ResultInterface $result, MessageBag $messages): WorkflowStateInterface
    {
        $content = $result->getContent();
        $state = $state->set($this->outputKey, $content);

        if (null !== $this->metadataKey) {
            $state = $state->set($this->metadataKey, $result->getMetadata()->all());
        }

        if (null !== $this->historyKey) {
            $conversation = \is_string($content) ? $messages->with(Message::ofAssistant($content)) : $messages;
            $state = $state->set($this->historyKey, $conversation);
        }

        return $state;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Agent\Execution\Runner;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\MessageStore\ConversationInput;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Agent\MessageStore\ReplaceableMessageStoreInterface;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Agent implements AgentInterface
{
    private readonly Runner $runner;

    /**
     * @param InputProcessorInterface[]  $inputProcessors
     * @param OutputProcessorInterface[] $outputProcessors
     * @param non-empty-string|Model     $model
     * @param bool                       $includeSources   exposes the sources collected during tool calling as `sources` result metadata
     * @param MessageStoreInterface|null $messageStore     continues the conversation stored under the id of the given messages and saves its progress
     */
    public function __construct(
        PlatformInterface $platform,
        private readonly string|Model $model,
        private readonly iterable $inputProcessors = [],
        private readonly iterable $outputProcessors = [],
        private readonly string $name = 'agent',
        ?ToolboxInterface $toolbox = null,
        ?ToolExecutorInterface $toolExecutor = null,
        ?int $maxToolCalls = 50,
        bool $includeSources = false,
        ?EventDispatcherInterface $eventDispatcher = null,
        ToolResultConverter $toolResultConverter = new ToolResultConverter(),
        private readonly ?MessageStoreInterface $messageStore = null,
    ) {
        if (null === $toolExecutor && $toolbox instanceof ToolboxInterface) {
            $toolExecutor = new SequentialToolExecutor($toolbox);
        }

        $this->runner = new Runner(
            $platform,
            $toolbox,
            $toolExecutor,
            $maxToolCalls,
            $includeSources,
            $eventDispatcher,
            $toolResultConverter,
            $name,
        );
    }

    /**
     * @return non-empty-string|Model
     */
    public function getModel(): string|Model
    {
        return $this->model;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Starts the agent and returns a lazy {@see ExecutionInterface} that is also the result it produces.
     *
     * Read it eagerly with `->getContent()`/`->getResult()`, iterate it to observe every model request, tool call
     * and streamed delta as an update, or register callbacks via `->onProgress(...)`. With the "stream" option
     * set, `->getContent()` yields the answer's deltas.
     *
     * @param array<string, mixed> $options
     *
     * @throws InvalidArgumentException When the platform returns a client error (4xx) indicating invalid request parameters
     * @throws RuntimeException         When the platform returns a server error (5xx) or network failure occurs
     * @throws ExceptionInterface       When the platform converter throws an exception
     */
    public function call(string|MessageBag|UserMessage $input, array $options = []): ExecutionInterface
    {
        $cancellation = new Cancellation();
        $factory = function () use ($input, $options, $cancellation): \Generator {
            $input = InputNormalizer::toMessageBag($input);
            if (null !== $this->messageStore) {
                $input = $this->messageStore instanceof ReplaceableMessageStoreInterface
                    ? $this->messageStore->resume($input)
                    : ConversationInput::appendNew($this->messageStore->load($input->getId()), $input);
                $this->messageStore->save($input);
            }

            $request = $this->prepareRequest($input, $options);
            $model = $request->getModel();
            $messages = $request->getMessageBag();
            $processedOptions = $request->getOptions();

            $result = null;
            $resultUpdate = null;
            $onToolRound = null;
            if (null !== $this->messageStore) {
                $store = $this->messageStore;
                $onToolRound = $store->save(...);
            }

            $onChange = function (MessageBag $previous, MessageBag $next, MessageBag $prepared): MessageBag {
                if ($previous->getMessages() === \array_slice($next->getMessages(), 0, \count($previous))) {
                    $this->messageStore?->save($next);

                    return $prepared->merge(new MessageBag(...\array_slice($next->getMessages(), \count($previous))));
                }

                $request = $next;
                if ($prepared->getMessages() !== $previous->getMessages()) {
                    $system = $prepared->getSystemMessage();
                    if (null === $system || $previous->withSystemMessage($system)->getMessages() !== $prepared->getMessages() || self::systemMessages($previous) !== self::systemMessages($next)) {
                        throw new RuntimeException('Cannot replace the conversation while preserving the changes made by its input processors.');
                    }
                    $request = $next->withSystemMessage($system);
                }

                if ($this->messageStore instanceof ReplaceableMessageStoreInterface) {
                    $this->messageStore->replace($previous, $next);
                } else {
                    $this->messageStore?->save($next);
                }

                return $request;
            };

            foreach ($this->runner->run($model, $messages, $processedOptions, $cancellation, $onToolRound, $input, $onChange) as $update) {
                if ($update instanceof ResultUpdate) {
                    $result = $update->getResult();
                    $resultUpdate = $update;

                    continue;
                }

                yield $update;
            }

            if ($cancellation->isRequested()) {
                return;
            }

            \assert($result instanceof ResultInterface && $resultUpdate instanceof ResultUpdate);

            $output = new Output($model, $result, $messages, $processedOptions);
            foreach ($this->outputProcessors as $outputProcessor) {
                if (!$outputProcessor instanceof OutputProcessorInterface) {
                    throw new InvalidArgumentException(\sprintf('Output processor "%s" must implement "%s".', $outputProcessor::class, OutputProcessorInterface::class));
                }

                if ($outputProcessor instanceof AgentAwareInterface) {
                    $outputProcessor->setAgent($this);
                }

                $outputProcessor->processOutput($output);
            }

            $finalResult = $output->getResult();
            $conversation = static function () use ($resultUpdate, $result, $finalResult): MessageBag {
                $conversation = $resultUpdate->getMessages($finalResult !== $result ? $finalResult : null);
                \assert(null !== $conversation);

                return $conversation;
            };

            if (null !== $this->messageStore) {
                $conversation = $conversation();
                $this->messageStore->save($conversation);
            }

            yield new ResultUpdate($finalResult, $conversation);
        };

        return new Execution($factory, true === ($options['stream'] ?? false), $cancellation);
    }

    /** @return list<SystemMessage> */
    private static function systemMessages(MessageBag $bag): array
    {
        $messages = [];
        foreach ($bag as $message) {
            if ($message instanceof SystemMessage) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /** @param array<string, mixed> $options */
    private function prepareRequest(MessageBag $input, array $options): Input
    {
        $request = new Input($this->getModel(), $input, $options);
        foreach ($this->inputProcessors as $inputProcessor) {
            if (!$inputProcessor instanceof InputProcessorInterface) {
                throw new InvalidArgumentException(\sprintf('Input processor "%s" must implement "%s".', $inputProcessor::class, InputProcessorInterface::class));
            }

            if ($inputProcessor instanceof AgentAwareInterface) {
                $inputProcessor->setAgent($this);
            }

            $inputProcessor->processInput($request);
        }

        return $request;
    }
}

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

use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\BatchItem;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * The run of an agent and the result it produces, returned by {@see \Symfony\AI\Agent\AgentInterface::call()}.
 *
 * The {@see MessageBag} is the state of the run.
 * Reading the status, the pending tool calls and the error never drives the agent and never throws.
 * Reading the result or the messages drives the agent.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @extends \IteratorAggregate<int, UpdateInterface>
 */
interface ExecutionInterface extends \IteratorAggregate, ResultInterface
{
    /**
     * Identifies this run, the conversation is identified by the id of its MessageBag.
     */
    public function getId(): AbstractUid&TimeBasedUidInterface;

    public function getStatus(): ExecutionStatus;

    /**
     * Whether tool calls of the model are unanswered, see {@see ExecutionStatus::WaitingForInput}.
     */
    public function isSuspended(): bool;

    /**
     * The tool calls of the final result, left unanswered by the agent.
     *
     * @return list<ToolCall>
     */
    public function getPendingToolCalls(): array;

    /**
     * The message of the error of a failed execution.
     */
    public function getError(): ?string;

    /**
     * Drives the execution to completion and returns the given messages followed by the new ones, ending with the answer.
     *
     * @throws LogicException when the agent doesn't provide the messages of its execution
     */
    public function getMessages(): MessageBag;

    /**
     * @param callable(Progress): void $callback
     */
    public function onProgress(callable $callback): self;

    /**
     * @param callable(Result): void $callback
     */
    public function onResult(callable $callback): self;

    /**
     * Whether the answer is streamed, in which case getContent() yields the deltas.
     */
    public function isStreamed(): bool;

    /**
     * Cancels the execution and its active HTTP response.
     */
    public function cancel(): void;

    /**
     * Drives the execution to completion and returns the final result.
     *
     * @throws ExceptionInterface
     */
    public function getResult(): ResultInterface;

    /**
     * Yields the streamed deltas of the answer, driving the execution as they arrive.
     *
     * @return \Generator<int, DeltaInterface, mixed, void>
     *
     * @throws LogicException when the answer is not streamed
     */
    public function asStream(): \Generator;

    /**
     * @throws ExceptionInterface
     */
    public function asText(): string;

    /**
     * A `response_format` given as a plain JSON schema has no class to deserialize into,
     * so the structured output stays the decoded array.
     *
     * @template T of object
     *
     * @param class-string<T>|null $class The class the structured output is expected to be an instance of
     *
     * @return ($class is null ? object|array<string, mixed> : T)
     *
     * @throws ExceptionInterface
     */
    public function asObject(?string $class = null): object|array;

    /**
     * @throws ExceptionInterface
     */
    public function asBinary(): string;

    /**
     * @throws ExceptionInterface
     */
    public function asFile(string $path): void;

    /**
     * @throws ExceptionInterface
     */
    public function asDataUri(?string $mimeType = null): string;

    /**
     * @return non-empty-list<ResultInterface>
     *
     * @throws ExceptionInterface
     */
    public function asMultiPart(): array;

    /**
     * @return iterable<BatchItem> one-shot traversal, read from the provider's result file as it is iterated
     *
     * @throws ExceptionInterface
     */
    public function asBatch(): iterable;

    /**
     * @return ToolCall[]
     *
     * @throws ExceptionInterface
     */
    public function asToolCalls(): array;

    /**
     * @return \Generator<TextDelta>
     *
     * @throws ExceptionInterface
     */
    public function asTextStream(): \Generator;

    /**
     * @return \Generator<object>
     *
     * @throws ExceptionInterface
     */
    public function asStreamedObject(): \Generator;

    /**
     * @return \Generator<mixed>
     *
     * @throws ExceptionInterface
     */
    public function asPartialJsonStream(): \Generator;
}

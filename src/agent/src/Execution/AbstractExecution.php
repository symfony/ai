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
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\TypedResultTrait;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * Base class for an execution whose state is read from somewhere else.
 *
 * The subclass provides the id, the status, the pending tool calls, the error, the messages and the result.
 * Everything else has a default.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
abstract class AbstractExecution implements ExecutionInterface
{
    use TypedResultTrait {
        asVectors as private;
        asReranking as private;
    }

    abstract public function getId(): AbstractUid&TimeBasedUidInterface;

    abstract public function getStatus(): ExecutionStatus;

    /**
     * @return list<ToolCall>
     */
    abstract public function getPendingToolCalls(): array;

    abstract public function getError(): ?string;

    abstract public function getMessages(): MessageBag;

    abstract public function getResult(): ResultInterface;

    public function isSuspended(): bool
    {
        return ExecutionStatus::WaitingForInput === $this->getStatus();
    }

    public function getContent(): string|iterable|object|null
    {
        return $this->getResult()->getContent();
    }

    public function getMetadata(): Metadata
    {
        return $this->getResult()->getMetadata();
    }

    public function getRawResult(): ?RawResultInterface
    {
        return $this->getResult()->getRawResult();
    }

    public function setRawResult(RawResultInterface $rawResult): void
    {
        $this->getResult()->setRawResult($rawResult);
    }

    public function getIterator(): \Generator
    {
        yield from [];
    }

    /**
     * The execution of an adapter has no live updates to report.
     */
    public function onProgress(callable $callback): static
    {
        return $this;
    }

    /**
     * The execution of an adapter has no live updates to report.
     */
    public function onResult(callable $callback): static
    {
        return $this;
    }

    public function isStreamed(): bool
    {
        return false;
    }

    public function asStream(): \Generator
    {
        throw new LogicException('The execution is not streamed.');
    }

    public function cancel(): void
    {
        throw new LogicException('This execution cannot be cancelled.');
    }
}

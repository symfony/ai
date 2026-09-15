<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * What executing a tool call shares across toolboxes: looking the tool up, the tool call events
 * and the error handling. Subclasses only say how a call turns into a value.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractToolbox implements ToolboxInterface
{
    public function __construct(
        protected readonly LoggerInterface $logger = new NullLogger(),
        protected readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    final public function execute(ToolCall $toolCall): ToolResult
    {
        $metadata = $this->getMetadata($toolCall);

        $event = new ToolCallRequested($toolCall, $metadata);
        $this->eventDispatcher?->dispatch($event);

        if ($event->isDenied()) {
            $this->logger->debug(\sprintf('Tool "%s" denied: %s', $toolCall->getName(), $event->getDenialReason()));

            return new ToolResult($toolCall, $event->getDenialReason() ?? 'Tool execution denied.');
        }

        if ($event->hasResult()) {
            return $event->getResult();
        }

        $tool = $this->getExecutable($metadata);
        $arguments = [];

        try {
            $this->logger->debug(\sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall->getArguments());

            $arguments = $this->resolveArguments($tool, $metadata, $toolCall);

            // Before the invocation: the tool writes into the collection while it runs.
            $sourceCollection = $this->prepareSources($tool);

            $result = new ToolResult(
                $toolCall,
                $this->invoke($tool, $metadata, $arguments),
                $sourceCollection,
            );

            $this->eventDispatcher?->dispatch(new ToolCallSucceeded($tool, $metadata, $arguments, $result));
        } catch (ToolExecutionExceptionInterface $e) {
            $this->eventDispatcher?->dispatch(new ToolCallFailed($tool, $metadata, $arguments, $e));
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf('Failed to execute tool "%s".', $toolCall->getName()), ['exception' => $e]);
            $this->eventDispatcher?->dispatch(new ToolCallFailed($tool, $metadata, $arguments, $e));
            throw ToolExecutionException::executionFailed($toolCall, $e);
        }

        return $result;
    }

    /**
     * The object executing the call, as reported to the tool call events.
     */
    abstract protected function getExecutable(Tool $metadata): object;

    /**
     * @return array<string, mixed>
     */
    abstract protected function resolveArguments(object $tool, Tool $metadata, ToolCall $toolCall): array;

    /**
     * @param array<string, mixed> $arguments
     */
    abstract protected function invoke(object $tool, Tool $metadata, array $arguments): mixed;

    protected function prepareSources(object $tool): ?SourceCollection
    {
        return null;
    }

    final protected function getMetadata(ToolCall $toolCall): Tool
    {
        foreach ($this->getTools() as $metadata) {
            if ($metadata->getName() === $toolCall->getName()) {
                return $metadata;
            }
        }

        throw ToolNotFoundException::notFoundForToolCall($toolCall);
    }
}

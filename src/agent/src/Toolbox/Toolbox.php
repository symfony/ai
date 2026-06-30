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
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Toolbox implements ToolboxInterface
{
    /**
     * Map of tools.
     *
     * @var array<string, array{metadata: Tool, tool: object}>
     */
    private array $map = [];

    /**
     * @param iterable<object> $tools - collection of references to executable tools
     */
    public function __construct(
        private readonly iterable $tools,
        private readonly ToolFactoryInterface $toolFactory = new ReflectionToolFactory(),
        private readonly ToolCallArgumentResolverInterface $argumentResolver = new ToolCallArgumentResolver(),
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    /**
     * @return Tool[]
     */
    public function getTools(): array
    {
        $this->initialize();

        return array_values(
            array_map(
                static fn (array $entry) => $entry['metadata'],
                $this->map
            )
        );
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        [$metadata, $tool] = $this->getMapEntry($toolCall);

        $event = new ToolCallRequested($toolCall, $metadata);
        $this->eventDispatcher?->dispatch($event);

        if ($event->isDenied()) {
            $this->logger->debug(\sprintf('Tool "%s" denied: %s', $toolCall->getName(), $event->getDenialReason()));

            return new ToolResult($toolCall, $event->getDenialReason() ?? 'Tool execution denied.');
        }

        if ($event->hasResult()) {
            return $event->getResult();
        }

        $this->logger->debug(\sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall->getArguments());

        try {
            $arguments = $this->argumentResolver->resolveArguments($metadata, $toolCall);
            $this->eventDispatcher?->dispatch(new ToolCallArgumentsResolved($tool, $metadata, $arguments));

            $sourceCollection = null;
            if ($tool instanceof HasSourcesInterface) {
                $tool->setSourceCollection($sourceCollection = new SourceCollection());
            }

            $result = new ToolResult(
                $toolCall,
                $tool->{$metadata->getReference()->getMethod()}(...$arguments),
                $sourceCollection,
            );

            $this->eventDispatcher?->dispatch(new ToolCallSucceeded($tool, $metadata, $arguments, $result));
        } catch (ToolExecutionExceptionInterface $e) {
            $this->eventDispatcher?->dispatch(new ToolCallFailed($tool, $metadata, $arguments, $e));
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf('Failed to execute tool "%s".', $toolCall->getName()), ['exception' => $e]);
            $this->eventDispatcher?->dispatch(new ToolCallFailed($tool, $metadata, $arguments ?? [], $e));
            throw ToolExecutionException::executionFailed($toolCall, $e);
        }

        return $result;
    }

    /**
     * @return array{0: Tool, 1: object}
     */
    private function getMapEntry(ToolCall $toolCall): array
    {
        $this->initialize();

        if (!isset($this->map[$toolCall->getName()])) {
            throw ToolNotFoundException::notFoundForToolCall($toolCall);
        }

        $entry = $this->map[$toolCall->getName()];

        return [$entry['metadata'], $entry['tool']];
    }

    private function initialize(): void
    {
        if ([] === $this->map) {
            $this->map = iterator_to_array($this->yieldMapEntries());
        }
    }

    /**
     * @return \Generator<string, array{metadata: Tool, tool: object}>
     */
    private function yieldMapEntries(): \Generator
    {
        foreach ($this->tools as $reference) {
            foreach ($this->toolFactory->getTool($reference) as $tool) {
                yield $tool->getName() => ['metadata' => $tool, 'tool' => $reference];
            }
        }
    }
}

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
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Toolbox implements ToolboxInterface
{
    /**
     * Cached list of tool metadata objects.
     *
     * @var Tool[]
     */
    private array $toolsMetadata;

    /**
     * Explicitly registered tool metadata via addTool().
     *
     * @var Tool[]
     */
    private array $explicitTools = [];

    /**
     * Maps tool name to the specific object instance registered via addTool().
     *
     * @var array<string, object>
     */
    private array $instanceMap = [];

    /**
     * Tracks classes registered via addTool() to skip attribute-based discovery.
     *
     * @var array<class-string, true>
     */
    private array $explicitClasses = [];

    /**
     * @param iterable<object> $tools
     */
    public function __construct(
        private readonly iterable $tools = [],
        private readonly ToolCallArgumentResolverInterface $argumentResolver = new ToolCallArgumentResolver(),
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly Factory $jsonSchemaFactory = new Factory(),
    ) {
    }

    public function addTool(object $tool, string $name, string $description, string $method = '__invoke'): self
    {
        try {
            $this->explicitTools[] = new Tool(
                new ExecutionReference($tool::class, $method),
                $name,
                $description,
                $this->jsonSchemaFactory->buildParameters($tool::class, $method),
            );
        } catch (\ReflectionException $e) {
            throw ToolConfigurationException::invalidMethod($tool::class, $method, $e);
        }

        $this->instanceMap[$name] = $tool;
        $this->explicitClasses[$tool::class] = true;

        unset($this->toolsMetadata);

        return $this;
    }

    public function getTools(): array
    {
        if (isset($this->toolsMetadata)) {
            return $this->toolsMetadata;
        }

        $toolsMetadata = $this->explicitTools;

        foreach ($this->tools as $tool) {
            if (isset($this->explicitClasses[$tool::class])) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($tool);
            $attributes = $reflectionClass->getAttributes(AsTool::class);

            foreach ($attributes as $attribute) {
                $asTool = $attribute->newInstance();

                try {
                    $toolsMetadata[] = new Tool(
                        new ExecutionReference($tool::class, $asTool->method),
                        $asTool->name,
                        $asTool->description,
                        $this->jsonSchemaFactory->buildParameters($tool::class, $asTool->method),
                    );
                } catch (\ReflectionException $e) {
                    throw ToolConfigurationException::invalidMethod($tool::class, $asTool->method, $e);
                }
            }
        }

        return $this->toolsMetadata = $toolsMetadata;
    }

    public function execute(ToolCall $toolCall): ToolResult
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

        try {
            $this->logger->debug(\sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall->getArguments());

            $arguments = $this->argumentResolver->resolveArguments($metadata, $toolCall);
            $this->eventDispatcher?->dispatch(new ToolCallArgumentsResolved($tool, $metadata, $arguments));

            if ($tool instanceof HasSourcesInterface) {
                $tool->setSourceCollection($sourceCollection = new SourceCollection());
            }

            $result = new ToolResult(
                $toolCall,
                $tool->{$metadata->getReference()->getMethod()}(...$arguments),
                $tool instanceof HasSourcesInterface ? $sourceCollection : null,
            );

            $this->eventDispatcher?->dispatch(new ToolCallSucceeded($tool, $metadata, $arguments, $result));
        } catch (ToolExecutionExceptionInterface $e) {
            $this->eventDispatcher?->dispatch(new ToolCallFailed($tool, $metadata, $arguments ?? [], $e));
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf('Failed to execute tool "%s".', $toolCall->getName()), ['exception' => $e]);
            $this->eventDispatcher?->dispatch(new ToolCallFailed($tool, $metadata, $arguments ?? [], $e));
            throw ToolExecutionException::executionFailed($toolCall, $e);
        }

        return $result;
    }

    private function getMetadata(ToolCall $toolCall): Tool
    {
        foreach ($this->getTools() as $metadata) {
            if ($metadata->getName() === $toolCall->getName()) {
                return $metadata;
            }
        }

        throw ToolNotFoundException::notFoundForToolCall($toolCall);
    }

    private function getExecutable(Tool $metadata): object
    {
        if (isset($this->instanceMap[$metadata->getName()])) {
            return $this->instanceMap[$metadata->getName()];
        }

        $className = $metadata->getReference()->getClass();
        foreach ($this->tools as $tool) {
            if ($tool instanceof $className) {
                return $tool;
            }
        }

        throw ToolNotFoundException::notFoundForReference($metadata->getReference());
    }
}

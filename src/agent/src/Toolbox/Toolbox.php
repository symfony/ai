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
final class Toolbox extends AbstractToolbox
{
    /**
     * List of tool metadata objects.
     *
     * @var Tool[]
     */
    private array $toolsMetadata;

    /**
     * Maps tool name to the specific object instance that was registered for it.
     *
     * @var array<string, object>
     */
    private array $instanceMap = [];

    /**
     * @param iterable<object> $tools
     */
    public function __construct(
        private readonly iterable $tools,
        private readonly ToolFactoryInterface $toolFactory = new ReflectionToolFactory(),
        private readonly ToolCallArgumentResolverInterface $argumentResolver = new ToolCallArgumentResolver(),
        LoggerInterface $logger = new NullLogger(),
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct($logger, $eventDispatcher);
    }

    public function getTools(): array
    {
        if (isset($this->toolsMetadata)) {
            return $this->toolsMetadata;
        }

        $toolsMetadata = [];
        foreach ($this->tools as $tool) {
            foreach ($this->toolFactory->getTool($tool) as $metadata) {
                $this->instanceMap[$metadata->getName()] = $tool;
                $toolsMetadata[] = $metadata;
            }
        }

        return $this->toolsMetadata = $toolsMetadata;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveArguments(object $tool, Tool $metadata, ToolCall $toolCall): array
    {
        $arguments = $this->argumentResolver->resolveArguments($metadata, $toolCall);

        $this->eventDispatcher?->dispatch(new ToolCallArgumentsResolved($tool, $metadata, $arguments));

        return $arguments;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function invoke(object $tool, Tool $metadata, array $arguments): mixed
    {
        return $tool->{$metadata->getReference()->getMethod()}(...$arguments);
    }

    protected function prepareSources(object $tool): ?SourceCollection
    {
        if (!$tool instanceof HasSourcesInterface) {
            return null;
        }

        $tool->setSourceCollection($sourceCollection = new SourceCollection());

        return $sourceCollection;
    }

    protected function getExecutable(Tool $metadata): object
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

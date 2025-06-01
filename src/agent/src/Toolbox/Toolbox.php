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
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Response\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Toolbox implements ToolboxInterface
{
    /**
     * List of executable tools.
     *
     * @var list<mixed>
     */
    private readonly array $tools;

    /**
     * List of tool metadata objects.
     *
     * @var Tool[]
     */
    private array $map;

    /**
     * @param iterable<mixed> $tools
     */
    public function __construct(
        private readonly ToolFactoryInterface $toolFactory,
        iterable $tools,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->tools = $tools instanceof \Traversable ? iterator_to_array($tools) : $tools;
    }

    public static function create(object ...$tools): self
    {
        return new self(new ReflectionToolFactory(), $tools);
    }

    public function getTools(): array
    {
        if (isset($this->map)) {
            return $this->map;
        }

        $map = [];
        foreach ($this->tools as $tool) {
            foreach ($this->toolFactory->getTool($tool::class) as $metadata) {
                $map[] = $metadata;
            }
        }

        return $this->map = $map;
    }

    public function execute(ToolCall $toolCall): mixed
    {
        $metadata = $this->getMetadata($toolCall);
        $tool = $this->getExecutable($metadata);

        try {
            $this->logger->debug(\sprintf('Executing tool "%s".', $toolCall->name), $toolCall->arguments);
            $result = $tool->{$metadata->reference->method}(...$toolCall->arguments);
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf('Failed to execute tool "%s".', $toolCall->name), ['exception' => $e]);
            throw ToolExecutionException::executionFailed($toolCall, $e);
        }

        return $result;
    }

    private function getMetadata(ToolCall $toolCall): Tool
    {
        foreach ($this->getTools() as $metadata) {
            if ($metadata->name === $toolCall->name) {
                return $metadata;
            }
        }

        throw ToolNotFoundException::notFoundForToolCall($toolCall);
    }

    private function getExecutable(Tool $metadata): object
    {
        foreach ($this->tools as $tool) {
            if ($tool instanceof $metadata->reference->class) {
                return $tool;
            }
        }

        throw ToolNotFoundException::notFoundForReference($metadata->reference);
    }
}

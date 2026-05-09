<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\ToolFactory;

use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MemoryToolFactory implements ToolFactoryInterface
{
    /**
     * @var array<string, Tool[]>
     */
    private array $tools = [];

    public function __construct(
        private readonly Factory $factory = new Factory(),
    ) {
    }

    /**
     * @param class-string|object $class
     */
    public function addTool(string|object $class, string $name, string $description, string $method = '__invoke'): self
    {
        $className = \is_object($class) ? $class::class : $class;
        $key = \is_object($class) ? 'obj_'.spl_object_id($class) : $className;

        try {
            $this->tools[$key][] = new Tool(
                new ExecutionReference($className, $method),
                $name,
                $description,
                $this->factory->buildParameters($className, $method),
            );
        } catch (\ReflectionException $e) {
            throw ToolConfigurationException::invalidMethod($className, $method, $e);
        }

        return $this;
    }

    public function getTool(object|string $reference): iterable
    {
        if (\is_object($reference)) {
            $key = 'obj_'.spl_object_id($reference);

            if (isset($this->tools[$key])) {
                yield from $this->tools[$key];

                return;
            }

            // Fall back to class name for tools registered by class string
            $key = $reference::class;
        } else {
            $key = $reference;
        }

        if (!isset($this->tools[$key])) {
            throw ToolException::invalidReference($key);
        }

        yield from $this->tools[$key];
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Invocation;

use Psr\Container\ContainerInterface;
use Symfony\AI\Mate\Discovery\Model\ToolDefinition;

/**
 * Invokes a discovered tool's handler method, resolving the handler instance from the DI
 * container when available and mapping/casting arguments from the provided bag.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolInvoker
{
    private ArgumentCaster $caster;

    public function __construct(
        private ContainerInterface $container,
        ?ArgumentCaster $caster = null,
    ) {
        $this->caster = $caster ?? new ArgumentCaster();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function invoke(ToolDefinition $tool, array $arguments): mixed
    {
        $method = new \ReflectionMethod($tool->handlerClass, $tool->handlerMethod);
        $instance = $this->resolveInstance($tool->handlerClass);
        $args = $this->caster->build($method, $arguments);

        return $method->invokeArgs($instance, $args);
    }

    /**
     * @param class-string $className
     */
    private function resolveInstance(string $className): object
    {
        if ($this->container->has($className)) {
            $instance = $this->container->get($className);
            if (\is_object($instance)) {
                return $instance;
            }
        }

        return new $className();
    }
}

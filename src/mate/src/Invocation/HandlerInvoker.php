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
use Symfony\AI\Mate\Exception\HandlerNotWiredException;

/**
 * Resolves a discovered handler from the container and calls it with cast arguments.
 *
 * Shared by {@see ToolInvoker} and {@see ResourceReader}, which differ only in what they wrap
 * around this step.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class HandlerInvoker
{
    private ArgumentCaster $caster;

    public function __construct(
        private ContainerInterface $container,
        ?ArgumentCaster $caster = null,
    ) {
        $this->caster = $caster ?? new ArgumentCaster();
    }

    /**
     * @param class-string         $className
     * @param array<string, mixed> $arguments
     */
    public function call(string $className, string $methodName, array $arguments): mixed
    {
        $method = new \ReflectionMethod($className, $methodName);
        $args = $this->caster->build($method, $arguments);

        return $method->invokeArgs($this->resolveInstance($className), $args);
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

        // Blind construction silently breaks any handler with dependencies; a missing service is
        // a wiring problem and should say so.
        throw new HandlerNotWiredException(\sprintf('Handler "%s" is not registered as a service. Run "vendor/bin/mate discover" and "composer dump-autoload", then check the class is inside a configured scan directory.', $className));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Discovery;

use Mcp\Capability\Discovery\Discoverer;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Discovery services to add to Symfony DI container.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ServiceDiscovery
{
    /**
     * Pre-register all discovered services in the container.
     * Call this BEFORE container->compile().
     *
     * @param array<string, array{dirs: string[], includes: string[]}> $bridges
     */
    public function registerServices(Discoverer $discoverer, ContainerBuilder $container, string $basePath, array $bridges): void
    {
        foreach ($bridges as $data) {
            $discoveryState = $discoverer->discover($basePath, $data['dirs']);
            foreach ($discoveryState->getTools() as $tool) {
                $this->maybeRegisterHandler($container, $tool->handler);
            }

            foreach ($discoveryState->getResources() as $resource) {
                $this->maybeRegisterHandler($container, $resource->handler);
            }

            foreach ($discoveryState->getPrompts() as $prompt) {
                $this->maybeRegisterHandler($container, $prompt->handler);
            }

            foreach ($discoveryState->getResourceTemplates() as $template) {
                $this->maybeRegisterHandler($container, $template->handler);
            }
        }
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function maybeRegisterHandler(ContainerBuilder $container, \Closure|array|string $handler): void
    {
        $className = $this->extractClassName($handler);
        if (null === $className) {
            return;
        }

        $this->registerService($container, $className);
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function extractClassName(\Closure|array|string $handler): ?string
    {
        if ($handler instanceof \Closure) {
            return null;
        }

        if (\is_string($handler)) {
            return class_exists($handler) ? $handler : null;
        }

        $class = $handler[0];
        if (\is_object($class)) {
            return $class::class;
        }

        return class_exists($class) ? $class : null;
    }

    private function registerService(ContainerBuilder $container, string $className): void
    {
        if ($container->has($className)) {
            $container->getDefinition($className)
                ->setPublic(true);

            return;
        }

        $container->register($className, $className)
            ->setAutowired(true)
            ->setPublic(true);
    }
}

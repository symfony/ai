<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\DependencyInjection;

use Symfony\AI\Agent\Toolbox\ChainToolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass that handles ToolboxInterface services in toolbox configurations.
 *
 * When a service implementing ToolboxInterface is configured as a tool, this pass
 * extracts it and wraps the toolbox with a ChainToolbox that combines both
 * regular tools and external toolboxes.
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
final class ToolboxCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('ai.toolbox') as $serviceId => $tags) {
            $this->processToolbox($container, $serviceId);
        }
    }

    private function processToolbox(ContainerBuilder $container, string $serviceId): void
    {
        $definition = $container->getDefinition($serviceId);

        // Get the first argument which contains the tools/toolboxes references
        // For ChildDefinition, we need to check if argument 0 has been replaced
        try {
            $toolsArgument = $definition->getArgument(0);
        } catch (\OutOfBoundsException) {
            return;
        }

        if (!\is_array($toolsArgument)) {
            return;
        }

        $tools = [];
        $externalToolboxes = [];

        foreach ($toolsArgument as $reference) {
            if (!$reference instanceof Reference) {
                $tools[] = $reference;
                continue;
            }

            $refId = (string) $reference;

            // Check if the referenced service implements ToolboxInterface
            if ($this->isToolboxInterface($container, $refId)) {
                $externalToolboxes[] = $reference;
            } else {
                $tools[] = $reference;
            }
        }

        // If no external toolboxes found, nothing to do
        if ([] === $externalToolboxes) {
            return;
        }

        $chainServiceId = $serviceId.'.chain_wrapper';
        $toolboxes = $externalToolboxes;

        if ([] !== $tools) {
            if ($definition instanceof ChildDefinition) {
                $definition->replaceArgument(0, $tools);
            } else {
                $definition->setArgument(0, $tools);
            }
            array_unshift($toolboxes, new Reference($chainServiceId.'.inner'));
        }

        $chainDefinition = (new Definition(ChainToolbox::class))
            ->setDecoratedService($serviceId, null, 100)
            ->setArguments([$toolboxes]);

        $container->setDefinition($chainServiceId, $chainDefinition);
    }

    private function isToolboxInterface(ContainerBuilder $container, string $serviceId): bool
    {
        if (!$container->hasDefinition($serviceId)) {
            return false;
        }

        $definition = $container->getDefinition($serviceId);
        $class = $definition->getClass();

        if (null === $class) {
            return false;
        }

        if (!class_exists($class)) {
            return false;
        }

        return is_subclass_of($class, ToolboxInterface::class);
    }
}

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

use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ProcessorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $contextProcessors = $container->findTaggedServiceIds('ai.agent.context_processor');

        foreach ($container->findTaggedServiceIds('ai.agent') as $serviceId => $tags) {
            $agentDefinition = $container->getDefinition($serviceId);

            // Skip MultiAgent services - they have a different constructor signature
            if (MultiAgent::class === $agentDefinition->getClass()) {
                continue;
            }

            $agentContextProcessors = [];
            foreach ($contextProcessors as $processorId => $processorTags) {
                foreach ($processorTags as $tag) {
                    if ('interface' === ($tag['tagged_by'] ?? null) && \count($processorTags) > 1) {
                        continue;
                    }

                    $agent = $tag['agent'] ?? null;
                    if (null === $agent || $agent === $serviceId) {
                        $priority = $tag['priority'] ?? 0;
                        $agentContextProcessors[] = [$priority, new Reference($processorId)];
                    }
                }
            }

            usort($agentContextProcessors, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

            $agentDefinition->setArgument('$contextProcessors', array_column($agentContextProcessors, 1));
        }
    }
}

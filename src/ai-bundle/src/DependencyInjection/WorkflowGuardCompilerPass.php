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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Attaches guards declared with the #[AsWorkflowGuard] attribute to their workflow definitions.
 *
 * Attribute-discovered guards are appended to the guards already listed under a workflow's
 * "guards" configuration key, so both registration styles can be combined.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowGuardCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $guards = $container->findTaggedServiceIds('ai.agent_workflow.guard');

        if ([] === $guards) {
            return;
        }

        foreach ($container->findTaggedServiceIds('ai.agent_workflow') as $workflowId => $workflowTags) {
            $workflowName = null;
            foreach ($workflowTags as $workflowTag) {
                if (isset($workflowTag['name'])) {
                    $workflowName = $workflowTag['name'];

                    break;
                }
            }

            if (null === $workflowName) {
                continue;
            }

            $matchedGuards = [];
            foreach ($guards as $guardId => $guardTags) {
                foreach ($guardTags as $guardTag) {
                    $targetWorkflow = $guardTag['workflow'] ?? null;
                    if (null !== $targetWorkflow && $targetWorkflow !== $workflowName) {
                        continue;
                    }

                    $matchedGuards[] = [$guardTag['priority'] ?? 0, new Reference($guardId)];

                    break;
                }
            }

            if ([] === $matchedGuards) {
                continue;
            }

            $definition = $container->getDefinition($workflowId);
            $configuredGuards = $definition->getArgument(4);

            // Config-listed guards carry no explicit priority, so they default to 0 and priority
            // ordering is global across both registration styles: an attribute guard with a priority
            // above 0 runs before config guards, one below 0 after them. usort is stable (PHP >= 8.0),
            // so equal-priority guards keep their declared order (config guards before attribute ones).
            $allGuards = [];
            foreach (\is_array($configuredGuards) ? $configuredGuards : [] as $configuredGuard) {
                $allGuards[] = [0, $configuredGuard];
            }
            foreach ($matchedGuards as $matchedGuard) {
                $allGuards[] = $matchedGuard;
            }

            usort($allGuards, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

            $definition->setArgument(4, array_column($allGuards, 1));
        }
    }
}

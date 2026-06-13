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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds the MCP tool adapters of an agent to its toolbox.
 *
 * A toolbox is handed either an explicit list of tool services or the `ai.tool` tagged
 * iterator, and neither can carry the agent's MCP adapters alongside it while the
 * extension is loading - the tagged services are only known once every bundle had its
 * say. Toolboxes with MCP servers configured are therefore tagged `ai.toolbox.mcp` with
 * their adapter ids, and this pass resolves the tag into the final tool list.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpToolboxCompilerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('ai.toolbox.mcp') as $toolboxId => $tags) {
            $adapters = [];
            foreach ($tags as $tag) {
                foreach ($tag['adapters'] as $adapterId) {
                    $adapters[] = new Reference($adapterId);
                }
            }

            if ([] === $adapters) {
                continue;
            }

            $definition = $container->getDefinition($toolboxId);
            $definition->clearTag('ai.toolbox.mcp');

            $tools = $definition->getArgument(0);
            if ($tools instanceof TaggedIteratorArgument) {
                $tools = $this->findAndSortTaggedServices($tools, $container);
            } elseif (!\is_array($tools)) {
                $tools = [];
            }

            $definition->replaceArgument(0, new IteratorArgument(array_merge($tools, $adapters)));
        }
    }
}

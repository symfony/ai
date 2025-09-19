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

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('ai.model_catalog')) {
            return;
        }

        $catalogDefinition = $container->getDefinition('ai.model_catalog');
        $models = [];

        foreach ($container->findTaggedServiceIds('ai.model.catalog') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $models[$tag['name']] = [
                    'class' => $tag['class'] ?? $container->getDefinition($serviceId)->getClass(),
                    'platform' => $tag['platform'],
                    'capabilities' => $tag['capabilities'],
                ];
            }
        }

        $catalogDefinition->setArgument(0, $models);
    }
}

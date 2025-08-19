<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\DependencyInjection\Compiler;

use Symfony\AI\Platform\Result\Metadata\TokenUsage\AsTokenUsageProcessor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
class RegisterTokenUsageProcessorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('ai.agent.output_processor') as $serviceId => $tags) {
            $serviceDefinition = $container->getDefinition($serviceId);
            $serviceClass = $serviceDefinition->getClass();

            if (!class_exists($serviceClass)) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($serviceClass);
            $attributes = $reflectionClass->getAttributes(AsTokenUsageProcessor::class);

            if (0 === \count($attributes)) {
                continue;
            }

            foreach ($attributes as $attribute) {
                $platform = $attribute->newInstance()->platform;
                $alias = \sprintf('ai.platform.token_usage_processor.%s', $platform);
                $container->setAlias($alias, $serviceId);
            }
        }
    }
}

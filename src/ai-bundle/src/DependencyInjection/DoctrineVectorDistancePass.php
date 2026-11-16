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

use Symfony\AI\Store\Bridge\Doctrine\Orm\Query\VectorDistance;
use Symfony\AI\Store\Bridge\Doctrine\OrmStore as DoctrineOrmStore;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the VECTOR_DISTANCE() DQL function on every entity manager, once a doctrine store is configured.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DoctrineVectorDistancePass implements CompilerPassInterface
{
    // VectorDistance::NAME would autoload the function node, which extends a class of the ORM
    private const FUNCTION_NAME = 'VECTOR_DISTANCE';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('doctrine.entity_managers') || !$this->hasDoctrineStore($container)) {
            return;
        }

        /** @var array<string, string> $entityManagers */
        $entityManagers = $container->getParameter('doctrine.entity_managers');

        foreach (array_keys($entityManagers) as $entityManager) {
            $configurationId = \sprintf('doctrine.orm.%s_configuration', $entityManager);

            if (!$container->hasDefinition($configurationId)) {
                continue;
            }

            $configuration = $container->getDefinition($configurationId);

            foreach ($configuration->getMethodCalls() as [$method, $arguments]) {
                if ('addCustomNumericFunction' === $method && self::FUNCTION_NAME === strtoupper((string) ($arguments[0] ?? ''))) {
                    continue 2;
                }
            }

            $configuration->addMethodCall('addCustomNumericFunction', [self::FUNCTION_NAME, VectorDistance::class]);
        }
    }

    private function hasDoctrineStore(ContainerBuilder $container): bool
    {
        foreach (array_keys($container->findTaggedServiceIds('ai.store')) as $storeId) {
            if (DoctrineOrmStore::class === $container->getDefinition($storeId)->getClass()) {
                return true;
            }
        }

        return false;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\DependencyInjection\DoctrineVectorDistancePass;
use Symfony\AI\Store\Bridge\Doctrine\Orm\Query\VectorDistance;
use Symfony\AI\Store\Bridge\Doctrine\OrmStore as DoctrineOrmStore;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class DoctrineVectorDistancePassTest extends TestCase
{
    public function testRegistersTheFunctionOnEveryEntityManager()
    {
        $container = $this->container(DoctrineOrmStore::class);

        (new DoctrineVectorDistancePass())->process($container);

        foreach (['default', 'archive'] as $entityManager) {
            $this->assertSame(
                [['addCustomNumericFunction', ['VECTOR_DISTANCE', VectorDistance::class]]],
                $container->getDefinition(\sprintf('doctrine.orm.%s_configuration', $entityManager))->getMethodCalls(),
            );
        }
    }

    public function testKeepsAFunctionTheApplicationRegistered()
    {
        $container = $this->container(DoctrineOrmStore::class);
        $container->getDefinition('doctrine.orm.default_configuration')->addMethodCall('addCustomNumericFunction', ['vector_distance', 'App\Doctrine\VectorDistance']);

        (new DoctrineVectorDistancePass())->process($container);

        $this->assertSame(
            [['addCustomNumericFunction', ['vector_distance', 'App\Doctrine\VectorDistance']]],
            $container->getDefinition('doctrine.orm.default_configuration')->getMethodCalls(),
        );
    }

    public function testLeavesTheEntityManagersAloneWithoutDoctrineStore()
    {
        $container = $this->container(InMemoryStore::class);

        (new DoctrineVectorDistancePass())->process($container);

        $this->assertSame([], $container->getDefinition('doctrine.orm.default_configuration')->getMethodCalls());
    }

    /**
     * @param class-string $storeClass
     */
    private function container(string $storeClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.entity_managers', [
            'default' => 'doctrine.orm.default_entity_manager',
            'archive' => 'doctrine.orm.archive_entity_manager',
        ]);
        $container->setDefinition('doctrine.orm.default_configuration', new Definition());
        $container->setDefinition('doctrine.orm.archive_configuration', new Definition());
        $container->setDefinition('ai.store.movies', (new Definition($storeClass))->addTag('ai.store'));

        return $container;
    }
}

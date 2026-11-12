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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ObjectDescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\PropertyDescriberInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class JsonSchemaDescriberWiringTest extends TestCase
{
    public function testDescribersCanBeInstantiatedWithoutTheFrameworkSerializerAndPropertyInfo()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setDefinition(LoggerInterface::class, new Definition(NullLogger::class));

        $bundle = new AiBundle();
        $bundle->build($container);
        $bundle->getContainerExtension()->load(['ai' => []], $container);

        $describerIds = array_keys($container->findTaggedServiceIds('ai.platform.json_schema.describer'));
        foreach ($describerIds as $id) {
            $container->getDefinition($id)->setPublic(true);
        }
        $container->compile();

        $this->assertNotEmpty($describerIds);
        foreach ($describerIds as $id) {
            $describer = $container->get($id);
            $this->assertTrue($describer instanceof ObjectDescriberInterface || $describer instanceof PropertyDescriberInterface, $id);
        }
    }
}

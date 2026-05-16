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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SchemaAttributeDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class SchemaProviderWiringTest extends TestCase
{
    public function testInterfaceIsRegisteredForAutoconfiguration()
    {
        $container = $this->buildContainer();

        $autoconfigured = $container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(SchemaProviderInterface::class, $autoconfigured);
        $this->assertArrayHasKey(
            'ai.platform.json_schema.provider',
            $autoconfigured[SchemaProviderInterface::class]->getTags(),
        );
    }

    public function testSchemaAttributeDescriberReceivesTaggedIterator()
    {
        $container = $this->buildContainer();

        $this->assertTrue($container->hasDefinition('ai.platform.json_schema.describer.schema_attribute'));

        $definition = $container->getDefinition('ai.platform.json_schema.describer.schema_attribute');
        $this->assertSame(SchemaAttributeDescriber::class, $definition->getClass());
        $this->assertArrayHasKey('ai.platform.json_schema.describer', $definition->getTags());

        $argument = $definition->getArgument(0);
        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
        $this->assertSame('ai.platform.json_schema.provider', $argument->getTag());
        $this->assertSame('key', $argument->getIndexAttribute());
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setDefinition(LoggerInterface::class, new Definition(NullLogger::class));

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load(['ai' => []], $container);

        return $container;
    }
}

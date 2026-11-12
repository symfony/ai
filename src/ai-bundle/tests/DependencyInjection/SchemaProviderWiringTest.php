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
use Symfony\AI\AiBundle\Tests\Fixture\JsonSchema\CategoryProvider;
use Symfony\AI\AiBundle\Tests\Fixture\JsonSchema\ProductDto;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\SchemaAttributeEnricher;
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

    public function testSchemaAttributeEnricherReceivesTaggedIterator()
    {
        $container = $this->buildContainer();

        $this->assertTrue($container->hasDefinition('ai.platform.json_schema.enricher.schema_attribute'));

        $definition = $container->getDefinition('ai.platform.json_schema.enricher.schema_attribute');
        $this->assertSame(SchemaAttributeEnricher::class, $definition->getClass());

        $argument = $definition->getArgument(0);
        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
        $this->assertSame('ai.platform.json_schema.provider', $argument->getTag());
        $this->assertSame('key', $argument->getIndexAttribute());
    }

    /**
     * End-to-end "just drop it in" check: a developer registers a SchemaProviderInterface
     * implementation as an ordinary autoconfigured service (id == FQCN, no manual tag) and
     * references it with #[Schema(provider: TheProvider::class)]. After a full container
     * compile, the enricher must resolve that provider by its FQCN and merge its fragment.
     */
    public function testDroppedInProviderIsResolvedByFqcnAndMergesItsFragment()
    {
        $container = $this->buildContainer();

        $container->register(CategoryProvider::class, CategoryProvider::class)
            ->setAutoconfigured(true)
            ->setArgument(0, ['electronics', 'books', 'toys']);

        $container->getDefinition('ai.platform.json_schema.enricher.schema_attribute')->setPublic(true);
        $container->compile();

        $enricher = $container->get('ai.platform.json_schema.enricher.schema_attribute');
        $this->assertInstanceOf(SchemaAttributeEnricher::class, $enricher);

        // ProductDto::$category carries #[Schema(provider: CategoryProvider::class)].
        $schema = $enricher->apply(new \ReflectionProperty(ProductDto::class, 'category'), ['type' => 'string']);

        $this->assertSame(['type' => 'string', 'enum' => ['electronics', 'books', 'toys']], $schema);
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setDefinition(LoggerInterface::class, new Definition(NullLogger::class));

        $bundle = new AiBundle();
        $bundle->build($container);
        $bundle->getContainerExtension()->load(['ai' => []], $container);

        return $container;
    }
}

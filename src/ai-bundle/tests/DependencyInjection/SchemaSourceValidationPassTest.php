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
use Symfony\AI\AiBundle\DependencyInjection\SchemaSourceValidationPass;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\AiBundle\Tests\Fixture\JsonSchema\CategoryProvider;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolWithoutSchemaSource;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolWithSchemaSource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class SchemaSourceValidationPassTest extends TestCase
{
    public function testPassesWhenAllProvidersAreRegistered()
    {
        $container = new ContainerBuilder();
        $container->setDefinition(CategoryProvider::class, new Definition(CategoryProvider::class))
            ->addTag('ai.platform.json_schema.provider');
        $container->setDefinition('app.provider.tag', new Definition(CategoryProvider::class))
            ->addTag('ai.platform.json_schema.provider');
        $container->setDefinition('tool.search', new Definition(ToolWithSchemaSource::class))
            ->addTag('ai.tool', ['name' => 'search', 'description' => 'Search']);

        (new SchemaSourceValidationPass())->process($container);

        $this->assertTrue(true);
    }

    public function testThrowsWhenProviderIsNotRegistered()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('tool.search', new Definition(ToolWithSchemaSource::class))
            ->addTag('ai.tool', ['name' => 'search', 'description' => 'Search']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Tool "tool.search" (%s::__invoke()) references SchemaSource provider "%s"', ToolWithSchemaSource::class, CategoryProvider::class));

        (new SchemaSourceValidationPass())->process($container);
    }

    public function testThrowsForUnknownArbitraryServiceId()
    {
        $container = new ContainerBuilder();
        $container->setDefinition(CategoryProvider::class, new Definition(CategoryProvider::class))
            ->addTag('ai.platform.json_schema.provider');
        $container->setDefinition('tool.search', new Definition(ToolWithSchemaSource::class))
            ->addTag('ai.tool', ['name' => 'search', 'description' => 'Search']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SchemaSource provider "app.provider.tag"');

        (new SchemaSourceValidationPass())->process($container);
    }

    public function testNoOpForToolWithoutSchemaSource()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('tool.noop', new Definition(ToolWithoutSchemaSource::class))
            ->addTag('ai.tool', ['name' => 'noop', 'description' => 'Noop']);

        (new SchemaSourceValidationPass())->process($container);

        $this->assertTrue(true);
    }

    public function testSkipsToolWithUnknownClass()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('tool.unknown', new Definition('App\\Nonexistent\\Tool'))
            ->addTag('ai.tool', ['name' => 'unknown', 'description' => 'Unknown']);

        (new SchemaSourceValidationPass())->process($container);

        $this->assertTrue(true);
    }
}

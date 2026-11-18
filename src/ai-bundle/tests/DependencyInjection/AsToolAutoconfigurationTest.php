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
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolOnMethodConflicting;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolOnMethods;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolWithMcpSchema;
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class AsToolAutoconfigurationTest extends TestCase
{
    public function testClassAttributeTagsTheConfiguredMethod()
    {
        $this->assertSame(
            [['name' => 'mcp_schema_tool', 'description' => 'Tool with MCP constraints', 'method' => 'search']],
            $this->toolTags(ToolWithMcpSchema::class),
        );
    }

    public function testMethodAttributesTagTheirMethods()
    {
        $this->assertSame(
            [
                ['name' => 'tool_current', 'description' => 'Current weather', 'method' => 'current'],
                ['name' => 'tool_forecast', 'description' => 'Weather forecast', 'method' => 'forecast'],
            ],
            $this->toolTags(ToolOnMethods::class),
        );
    }

    public function testMethodAttributeMustNotConfigureAnotherMethod()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The #[AsTool] attribute on "%s::current()" configures the method "other"', ToolOnMethodConflicting::class));

        $this->toolTags(ToolOnMethodConflicting::class);
    }

    /**
     * @param class-string $class
     *
     * @return list<array<string, mixed>>
     */
    private function toolTags(string $class): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setDefinition(LoggerInterface::class, new Definition(NullLogger::class));

        (new AiBundle())->getContainerExtension()->load(['ai' => []], $container);
        $container->register($class, $class)->setAutoconfigured(true);

        (new AttributeAutoconfigurationPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);

        return $container->getDefinition($class)->getTag('ai.tool');
    }
}

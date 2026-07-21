<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\DependencyInjection;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Icon;
use Mcp\Schema\ToolAnnotations;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class McpPassTest extends TestCase
{
    public function testRegistersToolWithPrecomputedMetadata()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(TimeTool::class, (new Definition(TimeTool::class))->addTag('mcp.tool', ['method' => 'getCurrentTime']));

        (new McpPass())->process($container);

        $calls = $this->callsNamed($container, 'addTool');
        $this->assertCount(1, $calls);

        $arguments = $calls[0][1];
        $this->assertSame([TimeTool::class, 'getCurrentTime'], $arguments[0]);
        $this->assertSame('current-time', $arguments[1]);
        $this->assertSame('Current Time', $arguments[2]);
        $this->assertSame('Returns the current time', $arguments[3]);

        $annotations = $arguments[4];
        $this->assertInstanceOf(Definition::class, $annotations);
        $this->assertSame(ToolAnnotations::class, $annotations->getClass());
        $this->assertTrue($annotations->getArgument(1)); // readOnlyHint

        $inputSchema = $arguments[5];
        $this->assertIsArray($inputSchema);
        $this->assertSame('object', $inputSchema['type']);
        $this->assertSame('string', $inputSchema['properties']['format']['type']);

        $icons = $arguments[6];
        $this->assertIsArray($icons);
        $this->assertCount(1, $icons);
        $this->assertInstanceOf(Definition::class, $icons[0]);
        $this->assertSame(Icon::class, $icons[0]->getClass());
        $this->assertSame('https://example.com/icon.png', $icons[0]->getArgument(0));

        $this->assertSame(['category' => 'time'], $arguments[7]);
        $this->assertSame(['type' => 'object', 'properties' => ['time' => ['type' => 'string']]], $arguments[8]);
    }

    public function testRegistersMultipleToolMethodsOfSameClass()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(MultiTool::class, (new Definition(MultiTool::class))
            ->addTag('mcp.tool', ['method' => 'first'])
            ->addTag('mcp.tool', ['method' => 'second']));

        (new McpPass())->process($container);

        $calls = $this->callsNamed($container, 'addTool');
        $this->assertCount(2, $calls);
        $this->assertSame([MultiTool::class, 'first'], $calls[0][1][0]);
        $this->assertSame([MultiTool::class, 'second'], $calls[1][1][0]);
    }

    public function testRegistersInvokableToolFromClassLevelAttribute()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(InvokableTool::class, (new Definition(InvokableTool::class))->addTag('mcp.tool', ['method' => '__invoke']));

        (new McpPass())->process($container);

        $calls = $this->callsNamed($container, 'addTool');
        $this->assertCount(1, $calls);
        $this->assertSame([InvokableTool::class, '__invoke'], $calls[0][1][0]);
        $this->assertSame('invokable-tool', $calls[0][1][1]);
    }

    public function testDeduplicatesSameMethodTaggedTwice()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(InvokableTool::class, (new Definition(InvokableTool::class))
            ->addTag('mcp.tool', ['method' => '__invoke'])
            ->addTag('mcp.tool'));

        (new McpPass())->process($container);

        $this->assertCount(1, $this->callsNamed($container, 'addTool'));
    }

    public function testRegistersPrompt()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(GreetingPrompt::class, (new Definition(GreetingPrompt::class))->addTag('mcp.prompt', ['method' => 'greeting']));

        (new McpPass())->process($container);

        $calls = $this->callsNamed($container, 'addPrompt');
        $this->assertCount(1, $calls);
        $this->assertSame([GreetingPrompt::class, 'greeting'], $calls[0][1][0]);
        $this->assertSame('greeting', $calls[0][1][1]);
    }

    public function testRegistersResource()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(ConfigResource::class, (new Definition(ConfigResource::class))->addTag('mcp.resource', ['method' => 'read']));

        (new McpPass())->process($container);

        $calls = $this->callsNamed($container, 'addResource');
        $this->assertCount(1, $calls);

        $arguments = $calls[0][1];
        $this->assertSame([ConfigResource::class, 'read'], $arguments[0]);
        $this->assertSame('config://app', $arguments[1]);
        $this->assertSame('app-config', $arguments[2]);
        $this->assertSame('application/json', $arguments[5]);
    }

    public function testRegistersResourceTemplate()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(UserTemplate::class, (new Definition(UserTemplate::class))->addTag('mcp.resource_template', ['method' => 'read']));

        (new McpPass())->process($container);

        $calls = $this->callsNamed($container, 'addResourceTemplate');
        $this->assertCount(1, $calls);

        $arguments = $calls[0][1];
        $this->assertSame([UserTemplate::class, 'read'], $arguments[0]);
        $this->assertSame('user://{id}', $arguments[1]);
        $this->assertSame('user', $arguments[2]);
    }

    public function testConvertsEmptySchemaObjectsToInlineDefinitions()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(ParameterlessTool::class, (new Definition(ParameterlessTool::class))->addTag('mcp.tool', ['method' => '__invoke']));

        (new McpPass())->process($container);

        $calls = $this->callsNamed($container, 'addTool');
        $this->assertCount(1, $calls);

        $inputSchema = $calls[0][1][5];
        $this->assertIsArray($inputSchema);
        $stdClassDefinitions = $this->collectStdClassDefinitions($inputSchema);
        $this->assertNotSame([], $stdClassDefinitions, 'Empty schema objects must be converted to inline \stdClass definitions');
    }

    public function testTaggedServiceWithoutAttributeOnlyJoinsLocator()
    {
        $container = $this->containerWithBuilder();
        // Mirrors services tagged by McpAppPass, which registers its tools itself.
        $container->setDefinition(PlainService::class, (new Definition(PlainService::class))->addTag('mcp.tool'));

        (new McpPass())->process($container);

        $this->assertSame([], $this->callsNamed($container, 'addTool'));
        $this->assertArrayHasKey(PlainService::class, $this->locatorServices($container));
    }

    public function testServiceLocatorIsKeyedByClassAndServiceId()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition('app.time_tool', (new Definition(TimeTool::class))->addTag('mcp.tool', ['method' => 'getCurrentTime']));

        (new McpPass())->process($container);

        $services = $this->locatorServices($container);

        $this->assertArrayHasKey(TimeTool::class, $services);
        $this->assertArrayHasKey('app.time_tool', $services);
        $this->assertInstanceOf(ServiceClosureArgument::class, $services[TimeTool::class]);
        $reference = $services[TimeTool::class]->getValues()[0];
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame('app.time_tool', (string) $reference);
    }

    public function testThrowsWhenExplicitTagMethodDoesNotExist()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(PlainService::class, (new Definition(PlainService::class))->addTag('mcp.tool', ['method' => 'missing']));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('The MCP service "%s" is tagged "mcp.tool" with method "missing", but class "%s" has no such method.', PlainService::class, PlainService::class));

        (new McpPass())->process($container);
    }

    public function testThrowsWhenExplicitTagMethodLacksAttribute()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(PlainService::class, (new Definition(PlainService::class))->addTag('mcp.tool', ['method' => 'run']));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('The MCP service "%s" is tagged "mcp.tool" with method "run", but "%s::run()" does not carry the #[%s] attribute.', PlainService::class, PlainService::class, McpTool::class));

        (new McpPass())->process($container);
    }

    public function testThrowsForTaggedServiceWithNonExistentClass()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition('broken_service', (new Definition('App\\DoesNotExist'))->addTag('mcp.tool'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The MCP service "broken_service" is tagged "mcp.tool" but maps to class "App\DoesNotExist" which does not exist.');

        (new McpPass())->process($container);
    }

    public function testSkipsAbstractDefinitions()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(TimeTool::class, (new Definition(TimeTool::class))->setAbstract(true)->addTag('mcp.tool', ['method' => 'getCurrentTime']));

        (new McpPass())->process($container);

        $this->assertSame([], $this->callsNamed($container, 'addTool'));
        $this->assertSame([], $container->getDefinition('mcp.server.builder')->getMethodCalls());
    }

    public function testDoesNothingWhenNoMcpServicesTagged()
    {
        $container = $this->containerWithBuilder();

        (new McpPass())->process($container);

        $this->assertSame([], $container->getDefinition('mcp.server.builder')->getMethodCalls());
    }

    public function testDoesNothingWhenNoServerBuilder()
    {
        $container = new ContainerBuilder();
        $container->setDefinition(TimeTool::class, (new Definition(TimeTool::class))->addTag('mcp.tool', ['method' => 'getCurrentTime']));

        (new McpPass())->process($container);

        $serviceLocators = array_filter(
            array_keys($container->getDefinitions()),
            static fn (string $id): bool => str_contains($id, 'service_locator'),
        );

        $this->assertSame([], $serviceLocators);
    }

    private function containerWithBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('mcp.server.builder', new Definition());

        return $container;
    }

    /**
     * @return list<array{0: string, 1: array<int, mixed>}>
     */
    private function callsNamed(ContainerBuilder $container, string $method): array
    {
        return array_values(array_filter(
            $container->getDefinition('mcp.server.builder')->getMethodCalls(),
            static fn (array $call): bool => $call[0] === $method,
        ));
    }

    /**
     * @return array<string, ServiceClosureArgument>
     */
    private function locatorServices(ContainerBuilder $container): array
    {
        $setContainerCalls = $this->callsNamed($container, 'setContainer');
        $this->assertCount(1, $setContainerCalls);

        $serviceLocatorId = (string) $setContainerCalls[0][1][0];
        $this->assertTrue($container->hasDefinition($serviceLocatorId));

        return $container->getDefinition($serviceLocatorId)->getArgument(0);
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return list<Definition>
     */
    private function collectStdClassDefinitions(array $value): array
    {
        $definitions = [];
        foreach ($value as $item) {
            if ($item instanceof Definition && \stdClass::class === $item->getClass()) {
                $definitions[] = $item;
            } elseif (\is_array($item)) {
                $definitions = array_merge($definitions, $this->collectStdClassDefinitions($item));
            }
        }

        return $definitions;
    }
}

class TimeTool
{
    #[McpTool(
        name: 'current-time',
        title: 'Current Time',
        description: 'Returns the current time',
        annotations: new ToolAnnotations(readOnlyHint: true),
        icons: [new Icon('https://example.com/icon.png')],
        meta: ['category' => 'time'],
        outputSchema: ['type' => 'object', 'properties' => ['time' => ['type' => 'string']]],
    )]
    public function getCurrentTime(string $format): string
    {
        return date($format);
    }
}

class MultiTool
{
    #[McpTool(name: 'first-tool')]
    public function first(string $input): string
    {
        return $input;
    }

    #[McpTool(name: 'second-tool')]
    public function second(string $input): string
    {
        return $input;
    }
}

#[McpTool(name: 'invokable-tool')]
class InvokableTool
{
    public function __invoke(int $count): string
    {
        return str_repeat('x', $count);
    }
}

class ParameterlessTool
{
    #[McpTool(name: 'parameterless-tool')]
    public function __invoke(): string
    {
        return 'ok';
    }
}

class GreetingPrompt
{
    #[McpPrompt(name: 'greeting')]
    public function greeting(string $name): string
    {
        return 'Hello '.$name;
    }
}

class ConfigResource
{
    #[McpResource(uri: 'config://app', name: 'app-config', mimeType: 'application/json')]
    public function read(): string
    {
        return '{}';
    }
}

class UserTemplate
{
    #[McpResourceTemplate(uriTemplate: 'user://{id}', name: 'user')]
    public function read(string $id): string
    {
        return $id;
    }
}

class PlainService
{
    public function run(): string
    {
        return 'ok';
    }
}

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
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Icon;
use Mcp\Schema\ToolAnnotations;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\McpBundle;
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

    public function testKeepsPercentSignsInToolMetadataLiteral()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(PercentTool::class, (new Definition(PercentTool::class))->addTag('mcp.tool', ['method' => 'search']));

        (new McpPass())->process($container);

        $arguments = $this->resolved($container, $this->callsNamed($container, 'addTool')[0][1]);
        $this->assertSame('discount', $arguments[1]);
        $this->assertSame('Get 50%off% today', $arguments[2]);
        $this->assertSame('Filter with "%" wildcards, 100% free', $arguments[3]);
        $this->assertSame(['%title%', null, null, null, null], $arguments[4]);
        $this->assertSame('Filter, "%" wildcard. Default "%" matches everything.', $arguments[5]['properties']['query']['description']);
        $this->assertSame('%', $arguments[5]['properties']['query']['default']);
        $this->assertSame([['https://example.com/icon%20one.png', null, null]], $arguments[6]);
        $this->assertSame(['%key%' => '%value%'], $arguments[7]);
        $this->assertSame(['type' => 'object', 'description' => 'Share in %percent%'], $arguments[8]);
    }

    public function testKeepsPercentSignsInPromptAndResourceMetadataLiteral()
    {
        $container = $this->containerWithBuilder();
        $container->setDefinition(PercentPrompt::class, (new Definition(PercentPrompt::class))->addTag('mcp.prompt', ['method' => 'discount']));
        $container->setDefinition(PercentResource::class, (new Definition(PercentResource::class))->addTag('mcp.resource', ['method' => 'read']));
        $container->setDefinition(PercentTemplate::class, (new Definition(PercentTemplate::class))->addTag('mcp.resource_template', ['method' => 'read']));

        (new McpPass())->process($container);

        $prompt = $this->resolved($container, $this->callsNamed($container, 'addPrompt')[0][1]);
        $this->assertSame('%discount%', $prompt[1]);
        $this->assertSame('50%off%', $prompt[2]);
        $this->assertSame('Get 50% off', $prompt[3]);

        $resource = $this->resolved($container, $this->callsNamed($container, 'addResource')[0][1]);
        $this->assertSame('file:///my%20docs/%readme%', $resource[1]);
        $this->assertSame('%readme%', $resource[2]);
        $this->assertSame('%title%', $resource[3]);
        $this->assertSame('100% up to date', $resource[4]);

        $template = $this->resolved($container, $this->callsNamed($container, 'addResourceTemplate')[0][1]);
        $this->assertSame('file:///my%20docs/{name}', $template[1]);
        $this->assertSame('%docs%', $template[2]);
        $this->assertSame('%title%', $template[3]);
        $this->assertSame('100% of the docs', $template[4]);
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
        $this->assertSame([], $container->getDefinition('mcp.server.default.builder')->getMethodCalls());
    }

    public function testDoesNothingWhenNoMcpServicesTagged()
    {
        $container = $this->containerWithBuilder();

        (new McpPass())->process($container);

        $this->assertSame([], $container->getDefinition('mcp.server.default.builder')->getMethodCalls());
    }

    public function testDoesNothingWhenNoServerConfigured()
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

    public function testPatternMatchingOnlySomeKindsIsAccepted()
    {
        // What "registry: ['App\\Mcp\\']" expands to: the same prefix on every kind, while the
        // application only has a tool under it.
        $prefix = 'Symfony\\AI\\McpBundle\\Tests\\DependencyInjection\\';
        $container = $this->containerWithBuilder(['default' => array_fill_keys(
            array_keys(McpBundle::ELEMENT_KINDS),
            [$prefix],
        )]);
        $container->setDefinition(TimeTool::class, (new Definition(TimeTool::class))->addTag('mcp.tool', ['method' => 'getCurrentTime']));

        (new McpPass())->process($container);

        $this->assertCount(1, $this->callsNamed($container, 'addTool'));
    }

    public function testPatternMatchingNothingAtAllIsRejected()
    {
        $container = $this->containerWithBuilder(['default' => ['tools' => ['App\\Typo\\']]]);
        $container->setDefinition(TimeTool::class, (new Definition(TimeTool::class))->addTag('mcp.tool', ['method' => 'getCurrentTime']));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"App\\Typo\\" under "mcp.servers.default.registry"');

        (new McpPass())->process($container);
    }

    /**
     * @param array<string, array<string, list<string>>> $elements
     */
    private function containerWithBuilder(array $elements = ['default' => []]): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($elements as $server => $lists) {
            $container->setDefinition('mcp.server.'.$server.'.builder', new Definition());

            foreach (array_keys(McpBundle::ELEMENT_KINDS) as $kind) {
                $elements[$server][$kind] ??= ['*'];
            }
        }

        $container->setParameter('mcp.servers.elements', $elements);

        return $container;
    }

    /**
     * Resolves the arguments the way the compiled container will see them, unwrapping inline definitions to their arguments.
     */
    private function resolved(ContainerBuilder $container, mixed $value): mixed
    {
        if ($value instanceof Definition) {
            return $this->resolved($container, $value->getArguments());
        }

        if (\is_array($value)) {
            $resolved = [];
            foreach ($value as $key => $item) {
                $resolved[$this->resolved($container, $key)] = $this->resolved($container, $item);
            }

            return $resolved;
        }

        $bag = $container->getParameterBag();

        return $bag->unescapeValue($bag->resolveValue($value));
    }

    /**
     * @return list<array{0: string, 1: array<int, mixed>}>
     */
    private function callsNamed(ContainerBuilder $container, string $method, string $server = 'default'): array
    {
        return array_values(array_filter(
            $container->getDefinition('mcp.server.'.$server.'.builder')->getMethodCalls(),
            static fn (array $call): bool => $call[0] === $method,
        ));
    }

    /**
     * @return array<string, ServiceClosureArgument>
     */
    private function locatorServices(ContainerBuilder $container, string $server = 'default'): array
    {
        $setContainerCalls = $this->callsNamed($container, 'setContainer', $server);
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

class PercentTool
{
    #[McpTool(
        name: 'discount',
        title: 'Get 50%off% today',
        description: 'Filter with "%" wildcards, 100% free',
        annotations: new ToolAnnotations(title: '%title%'),
        icons: [new Icon('https://example.com/icon%20one.png')],
        meta: ['%key%' => '%value%'],
        outputSchema: ['type' => 'object', 'description' => 'Share in %percent%'],
    )]
    public function search(
        #[Schema(description: 'Filter, "%" wildcard. Default "%" matches everything.')]
        string $query = '%',
    ): string {
        return $query;
    }
}

class PercentPrompt
{
    #[McpPrompt(name: '%discount%', title: '50%off%', description: 'Get 50% off')]
    public function discount(): string
    {
        return 'Get 50% off';
    }
}

class PercentResource
{
    #[McpResource(uri: 'file:///my%20docs/%readme%', name: '%readme%', title: '%title%', description: '100% up to date')]
    public function read(): string
    {
        return '100%';
    }
}

class PercentTemplate
{
    #[McpResourceTemplate(uriTemplate: 'file:///my%20docs/{name}', name: '%docs%', title: '%title%', description: '100% of the docs')]
    public function read(string $name): string
    {
        return $name;
    }
}

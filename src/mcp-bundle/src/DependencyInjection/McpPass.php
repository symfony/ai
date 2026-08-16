<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\DependencyInjection;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Discovery\DocBlockParser;
use Mcp\Capability\Discovery\SchemaGenerator;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Exception\ExceptionInterface as McpExceptionInterface;
use Mcp\Schema\Annotations;
use Mcp\Schema\Icon;
use Mcp\Schema\ToolAnnotations;
use Symfony\AI\McpBundle\App\McpAppReferenceHandler;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers container services carrying the SDK's MCP attributes with the MCP server builder.
 *
 * Replaces the SDK's file-based discovery: services are tagged via attribute autoconfiguration
 * (see {@see \Symfony\AI\McpBundle\McpBundle}), and this pass reflects each tagged method ONCE at
 * container compile time — deriving the tool input schema and passing the attribute metadata as
 * `addTool()`/`addResource()`/`addResourceTemplate()`/`addPrompt()` calls on the builder definition,
 * all cached in the dumped container. At runtime the SDK's {@see ReferenceHandler} resolves handler
 * instances lazily from a service locator keyed by class name, so element services are only
 * instantiated when actually invoked.
 */
final class McpPass implements CompilerPassInterface
{
    private const ELEMENT_TAGS = [
        'mcp.tool' => McpTool::class,
        'mcp.prompt' => McpPrompt::class,
        'mcp.resource' => McpResource::class,
        'mcp.resource_template' => McpResourceTemplate::class,
    ];

    private const TAG_KINDS = [
        'mcp.tool' => 'tools',
        'mcp.prompt' => 'prompts',
        'mcp.resource' => 'resources',
        'mcp.resource_template' => 'resource_templates',
    ];

    private const TAG_CALLS = [
        'mcp.tool' => 'addTool',
        'mcp.prompt' => 'addPrompt',
        'mcp.resource' => 'addResource',
        'mcp.resource_template' => 'addResourceTemplate',
    ];

    public function process(ContainerBuilder $container): void
    {
        /** @var array<string, array<string, list<string>>> $servers */
        $servers = $container->hasParameter('mcp.servers.elements') ? $container->getParameter('mcp.servers.elements') : [];
        if ([] === $servers) {
            return;
        }

        $builders = [];
        foreach (array_keys($servers) as $name) {
            $builders[$name] = $container->getDefinition('mcp.server.'.$name.'.builder');
        }

        $matcher = new ElementMatcher($servers);
        $schemaGenerator = new SchemaGenerator(new DocBlockParser());

        $serviceReferences = [];
        $registered = [];
        $unassigned = [];

        foreach (self::ELEMENT_TAGS as $tag => $attributeClass) {
            foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
                $definition = $container->getDefinition($serviceId);
                if ($definition->isAbstract()) {
                    continue;
                }

                $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $serviceId);
                if (!class_exists($class)) {
                    throw new LogicException(\sprintf('The MCP service "%s" is tagged "%s" but maps to class "%s" which does not exist.', $serviceId, $tag, $class));
                }

                $targets = $matcher->match(self::TAG_KINDS[$tag], $serviceId, $class);
                if ([] === $targets) {
                    // Not an error: a third-party bundle may ship MCP elements this application
                    // deliberately does not expose. Recorded so "debug:mcp" can report them.
                    $unassigned[self::TAG_KINDS[$tag]][] = $serviceId;
                    continue;
                }

                foreach ($targets as $server) {
                    // The SDK's ReferenceHandler resolves [class, method] handlers by class name; keep
                    // the service id as an additional key for handlers registered with a non-class id.
                    $serviceReferences[$server][$class] = new Reference($serviceId);
                    $serviceReferences[$server][$serviceId] ??= new Reference($serviceId);
                }

                foreach ($tags as $tagAttributes) {
                    $hasExplicitMethod = isset($tagAttributes['method']);
                    $method = $tagAttributes['method'] ?? '__invoke';
                    if (isset($registered[$tag][$class][$method])) {
                        continue;
                    }

                    // Tags without a "method" only join the handler locator when nothing is registrable
                    // (e.g. services tagged by McpAppPass, which registers its tools itself). Tags with an
                    // explicit "method" (every autoconfigured tag) are validated loudly.
                    if (!method_exists($class, $method)) {
                        if ($hasExplicitMethod) {
                            throw new LogicException(\sprintf('The MCP service "%s" is tagged "%s" with method "%s", but class "%s" has no such method.', $serviceId, $tag, $method, $class));
                        }

                        continue;
                    }

                    $attribute = $this->readAttribute($class, $method, $attributeClass);
                    if (null === $attribute) {
                        if ($hasExplicitMethod) {
                            throw new LogicException(\sprintf('The MCP service "%s" is tagged "%s" with method "%s", but "%s::%s()" does not carry the #[%s] attribute.', $serviceId, $tag, $method, $class, $method, $attributeClass));
                        }

                        continue;
                    }

                    $registered[$tag][$class][$method] = true;

                    // Reflection and schema generation run once per element, no matter how many
                    // servers expose it; only the resulting method call is repeated.
                    $arguments = match ($tag) {
                        'mcp.tool' => $this->toolArguments($class, $method, $attribute, $schemaGenerator, $serviceId),
                        'mcp.prompt' => $this->promptArguments($class, $method, $attribute),
                        'mcp.resource' => $this->resourceArguments($class, $method, $attribute),
                        'mcp.resource_template' => $this->resourceTemplateArguments($class, $method, $attribute),
                    };

                    foreach ($targets as $server) {
                        $builders[$server]->addMethodCall(self::TAG_CALLS[$tag], $this->copy($arguments));
                    }
                }
            }
        }

        $this->assertPatternsAreUsed($matcher);

        $container->setParameter('mcp.servers.unassigned', $unassigned);

        /** @var array<string, array<string, string>> $appHandlers */
        $appHandlers = $container->hasParameter('mcp.servers.app_handlers') ? $container->getParameter('mcp.servers.app_handlers') : [];
        /** @var array<string, array<string, array{template: string, meta: array<string, mixed>|null}>> $appTemplates */
        $appTemplates = $container->hasParameter('mcp.servers.app_tool_templates') ? $container->getParameter('mcp.servers.app_tool_templates') : [];

        // Resolved by class name at request time, so every server needs all of them.
        $completionProviders = [];
        foreach ($container->findTaggedServiceIds('mcp.completion_provider') as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            if ($definition->isAbstract()) {
                continue;
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $serviceId);
            $completionProviders[$class] = new Reference($serviceId);
            $completionProviders[$serviceId] ??= new Reference($serviceId);
        }

        foreach (array_keys($servers) as $server) {
            foreach ($appHandlers[$server] ?? [] as $key => $id) {
                $serviceReferences[$server][$key] ??= new Reference($id);
            }

            foreach ($completionProviders as $key => $reference) {
                $serviceReferences[$server][$key] ??= $reference;
            }

            if ([] === ($serviceReferences[$server] ?? [])) {
                continue;
            }

            $serviceLocatorRef = ServiceLocatorTagPass::register($container, $serviceReferences[$server]);
            $builders[$server]->addMethodCall('setContainer', [$serviceLocatorRef]);

            $this->configureAppReferenceHandler($container, $server, $serviceLocatorRef, $appTemplates[$server] ?? []);
        }
    }

    private function assertPatternsAreUsed(ElementMatcher $matcher): void
    {
        $unused = $matcher->getUnusedPatterns();
        if ([] === $unused) {
            return;
        }

        $messages = array_map(
            static fn (array $entry): string => \sprintf('"%s" under "mcp.servers.%s.%s"', $entry[2], $entry[0], $entry[1]),
            $unused,
        );

        throw new LogicException(\sprintf('The following MCP element patterns do not match any registered service: "%s". Elements are collected from container services carrying an MCP attribute, so check for a typo or make sure the class is registered as a service.', implode(', ', $messages)));
    }

    /**
     * Deep-copies the inline definitions in a method call's arguments so two server builders
     * never share the same Definition instance.
     *
     * @param list<mixed> $arguments
     *
     * @return list<mixed>
     */
    private function copy(array $arguments): array
    {
        foreach ($arguments as $key => $argument) {
            if ($argument instanceof Definition) {
                $copy = clone $argument;
                $copy->setArguments($this->copy(array_values($argument->getArguments())));
                $copy->setProperties($this->copyMap($argument->getProperties()));
                $arguments[$key] = $copy;
            } elseif (\is_array($argument)) {
                $arguments[$key] = $this->copyMap($argument);
            }
        }

        return $arguments;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function copyMap(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Definition) {
                $copy = clone $value;
                $copy->setArguments($this->copy(array_values($value->getArguments())));
                $copy->setProperties($this->copyMap($value->getProperties()));
                $values[$key] = $copy;
            } elseif (\is_array($value)) {
                $values[$key] = $this->copyMap($value);
            }
        }

        return $values;
    }

    /**
     * Reads the SDK attribute for the tagged method: method-level first, falling back to the
     * class-level attribute for invokable classes — mirroring the SDK's Discoverer semantics.
     *
     * @template T of McpTool|McpPrompt|McpResource|McpResourceTemplate
     *
     * @param class-string    $class
     * @param class-string<T> $attributeClass
     *
     * @return T|null
     */
    private function readAttribute(string $class, string $method, string $attributeClass): ?object
    {
        $reflection = new \ReflectionMethod($class, $method);

        $attributes = $reflection->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes && '__invoke' === $method) {
            $attributes = $reflection->getDeclaringClass()->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF);
        }

        return ([] !== $attributes) ? $attributes[0]->newInstance() : null;
    }

    /**
     * @return list<mixed>
     */
    private function toolArguments(string $class, string $method, McpTool $attribute, SchemaGenerator $schemaGenerator, string $serviceId): array
    {
        try {
            $inputSchema = $schemaGenerator->generate(new \ReflectionMethod($class, $method));
        } catch (McpExceptionInterface $e) {
            throw new LogicException(\sprintf('Cannot generate the input schema for MCP tool "%s::%s()" (service "%s"): "%s"', $class, $method, $serviceId, $e->getMessage()), 0, $e);
        }

        return [
            [$class, $method],
            $attribute->name,
            $attribute->title,
            $attribute->description,
            $this->toolAnnotationsDefinition($attribute->annotations),
            $this->dumpable($inputSchema),
            $this->iconDefinitions($attribute->icons),
            $this->dumpableOrNull($attribute->meta),
            $this->dumpableOrNull($attribute->outputSchema),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function promptArguments(string $class, string $method, McpPrompt $attribute): array
    {
        return [
            [$class, $method],
            $attribute->name,
            $attribute->title,
            $attribute->description,
            $this->iconDefinitions($attribute->icons),
            $this->dumpableOrNull($attribute->meta),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function resourceArguments(string $class, string $method, McpResource $attribute): array
    {
        return [
            [$class, $method],
            $attribute->uri,
            $attribute->name,
            $attribute->title,
            $attribute->description,
            $attribute->mimeType,
            $attribute->size,
            $this->annotationsDefinition($attribute->annotations),
            $this->iconDefinitions($attribute->icons),
            $this->dumpableOrNull($attribute->meta),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function resourceTemplateArguments(string $class, string $method, McpResourceTemplate $attribute): array
    {
        return [
            [$class, $method],
            $attribute->uriTemplate,
            $attribute->name,
            $attribute->title,
            $attribute->description,
            $attribute->mimeType,
            $this->annotationsDefinition($attribute->annotations),
            $this->dumpableOrNull($attribute->meta),
        ];
    }

    private function toolAnnotationsDefinition(?ToolAnnotations $annotations): ?Definition
    {
        if (null === $annotations) {
            return null;
        }

        return new Definition(ToolAnnotations::class, [
            $annotations->title,
            $annotations->readOnlyHint,
            $annotations->destructiveHint,
            $annotations->idempotentHint,
            $annotations->openWorldHint,
        ]);
    }

    private function annotationsDefinition(?Annotations $annotations): ?Definition
    {
        if (null === $annotations) {
            return null;
        }

        return new Definition(Annotations::class, [$annotations->audience, $annotations->priority]);
    }

    /**
     * @param Icon[]|null $icons
     *
     * @return list<Definition>|null
     */
    private function iconDefinitions(?array $icons): ?array
    {
        if (null === $icons) {
            return null;
        }

        return array_map(
            static fn (Icon $icon): Definition => new Definition(Icon::class, [$icon->src, $icon->mimeType, $icon->sizes]),
            array_values($icons),
        );
    }

    /**
     * @param array<array-key, mixed>|null $value
     *
     * @return array<array-key, mixed>|null
     */
    private function dumpableOrNull(?array $value): ?array
    {
        return null === $value ? null : $this->dumpable($value);
    }

    /**
     * Makes a metadata array safe for the dumped container by replacing embedded `\stdClass` values
     * (e.g. the schema generator's `{}` markers for free-form objects) with inline definitions.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private function dumpable(array $value): array
    {
        foreach ($value as $key => $item) {
            if ($item instanceof \stdClass) {
                $definition = new Definition(\stdClass::class);
                $properties = get_object_vars($item);
                if ([] !== $properties) {
                    $definition->setProperties($this->dumpable($properties));
                }
                $value[$key] = $definition;
            } elseif (\is_array($item)) {
                $value[$key] = $this->dumpable($item);
            }
        }

        return $value;
    }

    /**
     * Wires the {@see McpAppReferenceHandler} that renders MCP App tool templates (declared via
     * {@see \Symfony\AI\McpBundle\Attribute\AsMcpApp}::$toolTemplate or {@see \Symfony\AI\McpBundle\Attribute\AsMcpAppTool})
     * into the tool result's `html` field. It decorates the SDK default handler, which keeps the
     * reflection-derived input schema and argument mapping intact.
     */
    /**
     * @param array<string, mixed> $toolTemplates
     */
    private function configureAppReferenceHandler(ContainerBuilder $container, string $server, Reference $serviceLocatorRef, array $toolTemplates): void
    {
        if ([] === $toolTemplates) {
            return;
        }

        $handlerId = \sprintf('mcp.server.%s.app.reference_handler', $server);
        $innerHandler = new Definition(ReferenceHandler::class, [$serviceLocatorRef]);

        $container->register($handlerId, McpAppReferenceHandler::class)
            ->setArguments([
                $innerHandler,
                new Reference(McpAppRenderer::SERVICE_ID),
                $toolTemplates,
            ]);

        $container->getDefinition('mcp.server.'.$server.'.builder')
            ->addMethodCall('setReferenceHandler', [new Reference($handlerId)]);
    }
}

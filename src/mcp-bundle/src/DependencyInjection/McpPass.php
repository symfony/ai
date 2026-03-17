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

use Mcp\Capability\Attribute\CompletionProvider;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Discovery\DocBlockParser;
use Mcp\Capability\Discovery\SchemaGenerator;
use Mcp\Capability\Registry\Loader\ArrayLoader;
use Mcp\Exception\RuntimeException;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class McpPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.server.builder')) {
            return;
        }

        $docBlockParser = new DocBlockParser(logger: new NullLogger());
        $schemaGenerator = new SchemaGenerator($docBlockParser);

        $tools = [];
        $resources = [];
        $prompts = [];
        $resourceTemplates = [];
        $serviceReferences = [];

        $mcpTags = [
            'mcp.tool' => &$tools,
            'mcp.resource' => &$resources,
            'mcp.prompt' => &$prompts,
            'mcp.resource_template' => &$resourceTemplates,
        ];

        foreach ($mcpTags as $tag => &$collection) {
            foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tagAttributes) {
                $definition = $container->getDefinition($serviceId);
                $class = $definition->getClass() ?? $serviceId;

                /** @var string $serviceId */
                $serviceReferences[$serviceId] = new Reference($serviceId);

                $attribute = $tagAttributes[0] ?? [];
                $methodName = $attribute['method'] ?? '__invoke';

                try {
                    $reflection = new \ReflectionMethod($class, $methodName);

                    $entry = match ($tag) {
                        'mcp.tool' => $this->buildToolEntry($reflection, $schemaGenerator, $docBlockParser, $serviceId, $methodName),
                        'mcp.resource' => $this->buildResourceEntry($reflection, $docBlockParser, $serviceId, $methodName),
                        'mcp.prompt' => $this->buildPromptEntry($reflection, $docBlockParser, $serviceId, $methodName),
                        'mcp.resource_template' => $this->buildResourceTemplateEntry($reflection, $docBlockParser, $serviceId, $methodName),
                    };

                    if (null !== $entry) {
                        $collection[] = $entry;
                    }
                } catch (\Throwable $e) {
                    throw new RuntimeException(\sprintf('Error processing service "%s" with tag "%s": "%s".', $serviceId, $tag, $e->getMessage()), 0, $e);
                }
            }
        }

        if ([] === $serviceReferences) {
            return;
        }

        $serviceLocatorRef = ServiceLocatorTagPass::register($container, $serviceReferences);
        $container->getDefinition('mcp.server.builder')->addMethodCall('setContainer', [$serviceLocatorRef]);

        $loaderDefinition = (new Definition(ArrayLoader::class))
            ->setArguments([
                $tools,
                $resources,
                $resourceTemplates,
                $prompts,
            ])
            ->addTag('mcp.loader');

        $container->setDefinition('mcp.container_loader', $loaderDefinition);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildToolEntry(
        \ReflectionMethod $reflectionMethod,
        SchemaGenerator $schemaGenerator,
        DocBlockParser $docBlockParser,
        string $serviceId,
        string $methodName,
    ): ?array {
        $attrs = $reflectionMethod->getAttributes(McpTool::class, \ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attrs && '__invoke' === $methodName) {
            $attrs = $reflectionMethod->getDeclaringClass()->getAttributes(McpTool::class, \ReflectionAttribute::IS_INSTANCEOF);
        }

        if ([] === $attrs) {
            return null;
        }

        /** @var McpTool $attr */
        $attr = $attrs[0]->newInstance();
        $docBlock = $docBlockParser->parseDocBlock($reflectionMethod->getDocComment() ?: null);
        $shortName = $reflectionMethod->getDeclaringClass()->getShortName();

        return [
            'handler' => [$serviceId, $methodName],
            'name' => $attr->name ?? ('__invoke' === $methodName ? $shortName : $methodName),
            'description' => $attr->description ?? $docBlockParser->getDescription($docBlock),
            'inputSchema' => $schemaGenerator->generate($reflectionMethod),
            'outputSchema' => $attr->outputSchema,
            'annotations' => $attr->annotations?->jsonSerialize(),
            'icons' => null !== $attr->icons ? array_map(static fn ($i) => $i->jsonSerialize(), $attr->icons) : null,
            'meta' => $attr->meta,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildResourceEntry(
        \ReflectionMethod $reflection,
        DocBlockParser $docBlockParser,
        string $serviceId,
        string $methodName,
    ): ?array {
        $attrs = $reflection->getAttributes(McpResource::class, \ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attrs && '__invoke' === $methodName) {
            $attrs = $reflection->getDeclaringClass()->getAttributes(McpResource::class, \ReflectionAttribute::IS_INSTANCEOF);
        }
        if ([] === $attrs) {
            return null;
        }

        /** @var McpResource $attr */
        $attr = $attrs[0]->newInstance();
        $docBlock = $docBlockParser->parseDocBlock($reflection->getDocComment() ?: null);
        $shortName = $reflection->getDeclaringClass()->getShortName();

        return [
            'handler' => [$serviceId, $methodName],
            'uri' => $attr->uri,
            'name' => $attr->name ?? ('__invoke' === $methodName ? $shortName : $methodName),
            'description' => $attr->description ?? $docBlockParser->getDescription($docBlock),
            'mimeType' => $attr->mimeType,
            'size' => $attr->size,
            'annotations' => $attr->annotations?->jsonSerialize(),
            'icons' => null !== $attr->icons ? array_map(static fn ($i) => $i->jsonSerialize(), $attr->icons) : null,
            'meta' => $attr->meta,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPromptEntry(
        \ReflectionMethod $reflection,
        DocBlockParser $docBlockParser,
        string $serviceId,
        string $methodName,
    ): ?array {
        $attrs = $reflection->getAttributes(McpPrompt::class, \ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attrs && '__invoke' === $methodName) {
            $attrs = $reflection->getDeclaringClass()->getAttributes(McpPrompt::class, \ReflectionAttribute::IS_INSTANCEOF);
        }
        if ([] === $attrs) {
            return null;
        }

        /** @var McpPrompt $attr */
        $attr = $attrs[0]->newInstance();
        $docBlock = $docBlockParser->parseDocBlock($reflection->getDocComment() ?: null);
        $paramTags = $docBlockParser->getParamTags($docBlock);
        $shortName = $reflection->getDeclaringClass()->getShortName();

        $arguments = [];
        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                continue;
            }
            $paramTag = $paramTags['$'.$param->getName()] ?? null;
            $arguments[] = [
                'name' => $param->getName(),
                'description' => $paramTag ? trim((string) $paramTag->getDescription()) : null,
                'required' => !$param->isOptional() && !$param->isDefaultValueAvailable(),
            ];
        }

        return [
            'handler' => [$serviceId, $methodName],
            'name' => $attr->name ?? ('__invoke' === $methodName ? $shortName : $methodName),
            'description' => $attr->description ?? $docBlockParser->getDescription($docBlock),
            'arguments' => $arguments,
            'icons' => null !== $attr->icons ? array_map(static fn ($i) => $i->jsonSerialize(), $attr->icons) : null,
            'meta' => $attr->meta,
            'completionProviders' => $this->extractCompletionProviders($reflection),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildResourceTemplateEntry(
        \ReflectionMethod $reflection,
        DocBlockParser $docBlockParser,
        string $serviceId,
        string $methodName,
    ): ?array {
        $attrs = $reflection->getAttributes(McpResourceTemplate::class, \ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attrs && '__invoke' === $methodName) {
            $attrs = $reflection->getDeclaringClass()->getAttributes(McpResourceTemplate::class, \ReflectionAttribute::IS_INSTANCEOF);
        }
        if ([] === $attrs) {
            return null;
        }

        /** @var McpResourceTemplate $attr */
        $attr = $attrs[0]->newInstance();
        $docBlock = $docBlockParser->parseDocBlock($reflection->getDocComment() ?: null);
        $shortName = $reflection->getDeclaringClass()->getShortName();

        return [
            'handler' => [$serviceId, $methodName],
            'uriTemplate' => $attr->uriTemplate,
            'name' => $attr->name ?? ('__invoke' === $methodName ? $shortName : $methodName),
            'description' => $attr->description ?? $docBlockParser->getDescription($docBlock),
            'mimeType' => $attr->mimeType,
            'annotations' => $attr->annotations?->jsonSerialize(),
            'meta' => $attr->meta,
            'completionProviders' => $this->extractCompletionProviders($reflection),
        ];
    }

    /**
     * Serializes CompletionProvider parameter attributes into scalar arrays.
     *
     * @return array<string, array{type: string, values?: list<int|float|string>, class?: class-string}>
     */
    private function extractCompletionProviders(\ReflectionMethod $reflection): array
    {
        $providers = [];
        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                continue;
            }
            $attrs = $param->getAttributes(CompletionProvider::class, \ReflectionAttribute::IS_INSTANCEOF);
            if ([] === $attrs) {
                continue;
            }
            /** @var CompletionProvider $cp */
            $cp = $attrs[0]->newInstance();
            if (null !== $cp->values) {
                $providers[$param->getName()] = ['type' => 'list', 'values' => $cp->values];
            } elseif (null !== $cp->enum) {
                $providers[$param->getName()] = ['type' => 'enum', 'class' => $cp->enum];
            } elseif (null !== $cp->providerClass) {
                $providers[$param->getName()] = ['type' => 'class', 'class' => $cp->providerClass];
            }
        }

        return $providers;
    }
}

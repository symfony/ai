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

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Discovery\DocBlockParser;
use Mcp\Capability\Discovery\SchemaGenerator;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\Loader\ContainerLoader;
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

        $serviceReferences = [];

        $mcpTags = [
            'mcp.tool' => &$tools,
        ];

        foreach ($mcpTags as $tag => &$collection) {
            foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tagAttributes) {
                $definition = $container->getDefinition($serviceId);
                $class = $definition->getClass() ?? $serviceId;

                if (!isset($serviceReferences[$class])) {
                    $serviceReferences[$class] = new Reference($serviceId);
                }

                foreach ($tagAttributes as $attribute) {
                    $methodName = $attribute['method'] ?? '__invoke';

                    try {
                        $reflection = new \ReflectionMethod($class, $methodName);

                        $entry = match ($tag) {
                            'mcp.tool' => $this->buildToolEntry($reflection, $schemaGenerator, $docBlockParser, $class, $methodName),
                            default => null,
                        };

                        if (null !== $entry) {
                            $collection[] = $entry;
                        }
                    } catch (\Throwable $e) {
                        throw new \RuntimeException(\sprintf('Error processing service "%s" with tag "%s": %s', $serviceId, $tag, $e->getMessage()), 0, $e);
                    }
                }
            }
        }

        if ([] === $serviceReferences) {
            return;
        }

        $serviceLocatorRef = ServiceLocatorTagPass::register($container, $serviceReferences);
        $container->getDefinition('mcp.server.builder')->addMethodCall('setContainer', [$serviceLocatorRef]);

        $loaderDefinition = (new Definition(ContainerLoader::class))
            ->setArguments([
                $tools,
                [],
                [],
                [],
            ])
            ->addTag('mcp.loader');

        $container->setDefinition('mcp.container_loader', $loaderDefinition);
    }

    /**
     * @param class-string $class
     */
    private function buildToolEntry(
        \ReflectionMethod $reflectionMethod,
        SchemaGenerator $schemaGenerator,
        DocBlockParser $docBlockParser,
        string $class,
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
            'class' => $class,
            'method' => $methodName,
            'name' => $attr->name ?? ('__invoke' === $methodName ? $shortName : $methodName),
            'description' => $attr->description ?? $docBlockParser->getDescription($docBlock),
            'inputSchema' => $schemaGenerator->generate($reflectionMethod),
            'outputSchema' => $attr->outputSchema,
            'annotations' => $attr->annotations?->jsonSerialize(),
            'icons' => null !== $attr->icons ? array_map(static fn ($i) => $i->jsonSerialize(), $attr->icons) : null,
            'meta' => $attr->meta,
        ];
    }
}

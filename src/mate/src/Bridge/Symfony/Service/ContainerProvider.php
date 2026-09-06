<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Service;

use Symfony\AI\Mate\Bridge\Symfony\Exception\FileNotFoundException;
use Symfony\AI\Mate\Bridge\Symfony\Exception\XmlContainerCouldNotBeLoadedException;
use Symfony\AI\Mate\Bridge\Symfony\Exception\XmlContainerPathIsNotConfiguredException;
use Symfony\AI\Mate\Bridge\Symfony\Model\Container;
use Symfony\AI\Mate\Bridge\Symfony\Model\ServiceDefinition;
use Symfony\AI\Mate\Bridge\Symfony\Model\ServiceTag;

/**
 * This will parse an App_KernelDevDebugContainer.xml and return value objects.
 *
 * @phpstan-import-type ParsedArgument from ServiceDefinition
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ContainerProvider
{
    /**
     * Argument node types the dumper writes for a reference to another service.
     *
     * @var list<string>
     */
    private const SERVICE_ARGUMENT_TYPES = ['service', 'service_closure'];

    /**
     * Argument node types that carry nested `<argument>` children rather than a value. A
     * messenger bus keeps its middleware in an `iterator`, which is why this matters.
     *
     * @var list<string>
     */
    private const COLLECTION_ARGUMENT_TYPES = ['collection', 'iterator', 'service_locator'];

    /**
     * Argument node types that stand for "every service carrying this tag". They hold no
     * value, only the tag name, and that name is wiring rather than data.
     *
     * @var list<string>
     */
    private const TAGGED_ARGUMENT_TYPES = ['tagged_iterator', 'tagged_locator'];

    /**
     * Argument node types whose text content is meant to stay a string, so it must not be
     * coerced back into a bool, null or number.
     *
     * @var list<string>
     */
    private const STRING_ARGUMENT_TYPES = ['string', 'binary', 'constant', 'expression', 'abstract', 'env_closure'];
    /**
     * @var array<string, Container>
     */
    private array $container = [];

    /**
     * @throws XmlContainerCouldNotBeLoadedException
     */
    public function getContainer(string $containerXmlPath): Container
    {
        if (null === ($this->container[$containerXmlPath] ?? null)) {
            $this->container[$containerXmlPath] = $this->read($containerXmlPath);
        }

        return $this->container[$containerXmlPath];
    }

    /**
     * @throws XmlContainerCouldNotBeLoadedException
     */
    private function read(string $containerXmlPath): Container
    {
        $xml = $this->parseXml($containerXmlPath);

        /** @var array<string, ServiceDefinition> $services */
        $services = [];
        /** @var ServiceDefinition[] $aliases */
        $aliases = [];

        if (isset($xml->services) && \count($xml->services) > 0) {
            foreach ($xml->services->service as $def) {
                /** @var \SimpleXMLElement $attrs */
                $attrs = $def->attributes();
                if (!isset($attrs->id)) {
                    continue;
                }

                $calls = [];
                foreach ($def->call as $call) {
                    $calls[] = (string) $call->attributes()->method;
                }

                $serviceTags = [];
                foreach ($def->tag as $tag) {
                    /** @var array<string, string> $tagAttrs */
                    $tagAttrs = ((array) $tag->attributes())['@attributes'] ?? [];
                    $tagName = $tagAttrs['name'];
                    unset($tagAttrs['name']);

                    $serviceTags[] = new ServiceTag($tagName, $tagAttrs);
                }

                /** @var ?class-string $class */
                $class = isset($attrs->class) ? (string) $attrs->class : null;
                $constructor = '__construct';
                if (isset($attrs->constructor)) {
                    $constructor = (string) $attrs->constructor;
                }
                $constructor = [$class, $constructor];
                if (isset($def->factory)) {
                    $constructor = [(string) $def->factory->attributes()->class, (string) $def->factory->attributes()->method];
                }

                $service = new ServiceDefinition(
                    self::cleanServiceId((string) $attrs->id),
                    $class,
                    isset($attrs->alias) ? self::cleanServiceId((string) $attrs->alias) : null,
                    $calls,
                    $serviceTags,
                    $constructor,
                    $this->parseArguments($def),
                );

                if (null === $service->getAlias()) {
                    $services[$service->getId()] = $service;
                } else {
                    $aliases[] = $service;
                }
            }
        }

        foreach ($aliases as $service) {
            $alias = $service->getAlias();
            if (null === $alias || !isset($services[$alias])) {
                continue;
            }

            $services[$service->getId()] = new ServiceDefinition(
                $service->getId(),
                $services[$alias]->getClass(),
                null,
                $services[$alias]->getCalls(),
                $services[$alias]->getTags(),
                $services[$alias]->getConstructor(),
                $services[$alias]->getArguments(),
            );
        }

        return new Container($services);
    }

    /**
     * Reads the direct `<argument>` children of a node.
     *
     * Symfony's XmlDumper writes constructor arguments here
     * (`convertParameters($definition->getArguments(), 'argument')`), so the wiring is in
     * the dump already — it was simply never read.
     *
     * @return list<ParsedArgument>
     */
    private function parseArguments(\SimpleXMLElement $node): array
    {
        $arguments = [];

        foreach ($node->argument as $argument) {
            /** @var \SimpleXMLElement $attrs */
            $attrs = $argument->attributes();
            $type = isset($attrs->type) ? (string) $attrs->type : null;
            $key = isset($attrs->key) ? (string) $attrs->key : null;

            if (null !== $type && \in_array($type, self::SERVICE_ARGUMENT_TYPES, true)) {
                $arguments[] = [
                    'key' => $key,
                    'type' => 'service',
                    'value' => $this->referencedService($argument, $attrs),
                    'literal' => false,
                ];

                continue;
            }

            if (null !== $type && \in_array($type, self::COLLECTION_ARGUMENT_TYPES, true)) {
                $arguments[] = [
                    'key' => $key,
                    'type' => 'collection',
                    'value' => $this->parseArguments($argument),
                    'literal' => false,
                ];

                continue;
            }

            if (null !== $type && \in_array($type, self::TAGGED_ARGUMENT_TYPES, true)) {
                $arguments[] = [
                    'key' => $key,
                    'type' => 'scalar',
                    'value' => \sprintf('all services tagged "%s"', isset($attrs->tag) ? (string) $attrs->tag : ''),
                    'literal' => false,
                ];

                continue;
            }

            $arguments[] = [
                'key' => $key,
                'type' => 'scalar',
                'value' => $this->castScalar((string) $argument, $type),
                'literal' => true,
            ];
        }

        return $arguments;
    }

    /**
     * A service argument normally carries the referenced id. An inline (anonymous)
     * definition has no id, and then the nested `<service>` node's class is the only
     * identity there is.
     */
    private function referencedService(\SimpleXMLElement $argument, \SimpleXMLElement $attrs): ?string
    {
        if (isset($attrs->id)) {
            return self::cleanServiceId((string) $attrs->id);
        }

        if (isset($argument->service)) {
            $inline = $argument->service->attributes();
            if (isset($inline->class)) {
                return (string) $inline->class;
            }
        }

        return null;
    }

    /**
     * The dumper writes `true`, `false`, `null` and numbers as bare text and marks anything
     * that only looks like one with `type="string"`, so the original type is recoverable —
     * and worth recovering, because `\"30\"` and `30` read differently to whoever is
     * diagnosing the wiring.
     */
    private function castScalar(string $text, ?string $type): mixed
    {
        if (null !== $type && \in_array($type, self::STRING_ARGUMENT_TYPES, true)) {
            return $text;
        }

        return match ($text) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => is_numeric($text) ? $text + 0 : $text,
        };
    }

    private function cleanServiceId(string $id): string
    {
        return str_starts_with($id, '.') ? mb_substr($id, 1) : $id;
    }

    /**
     * @throws XmlContainerCouldNotBeLoadedException
     * @throws FileNotFoundException
     */
    private function parseXml(string $containerXmlPath): \SimpleXMLElement
    {
        if ('' === $containerXmlPath) {
            throw new XmlContainerPathIsNotConfiguredException('Failed to configure path to Symfony container. You passed an empty string.');
        }

        if (!file_exists($containerXmlPath)) {
            throw new FileNotFoundException(\sprintf('Container XML at "%s" does not exist', $containerXmlPath));
        }

        $fileContents = file_get_contents($containerXmlPath);
        if (false === $fileContents) {
            throw new XmlContainerCouldNotBeLoadedException(\sprintf('Container "%s" does not exist', $containerXmlPath));
        }

        $xml = @simplexml_load_string($fileContents);
        if (false === $xml) {
            throw new XmlContainerCouldNotBeLoadedException(\sprintf('Container "%s" cannot be parsed', $containerXmlPath));
        }

        return $xml;
    }
}

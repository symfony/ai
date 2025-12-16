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
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ContainerProvider
{
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
                );

                if (null === $service->alias) {
                    $services[$service->id] = $service;
                } else {
                    $aliases[] = $service;
                }
            }
        }

        foreach ($aliases as $service) {
            $alias = $service->alias;
            if (null === $alias || !isset($services[$alias])) {
                continue;
            }

            $services[$service->id] = new ServiceDefinition(
                $service->id,
                $services[$alias]->class,
                null,
                $services[$alias]->calls,
                $services[$alias]->tags,
                $services[$alias]->constructor,
            );
        }

        return new Container($services);
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
            throw XmlContainerPathIsNotConfiguredException::emptyPath();
        }

        if (!file_exists($containerXmlPath)) {
            throw FileNotFoundException::forContainerXml($containerXmlPath);
        }

        $fileContents = file_get_contents($containerXmlPath);
        if (false === $fileContents) {
            throw XmlContainerCouldNotBeLoadedException::forContainerDoesNotExist($containerXmlPath);
        }

        $xml = @simplexml_load_string($fileContents);
        if (false === $xml) {
            throw XmlContainerCouldNotBeLoadedException::forContainerCannotBeParsed($containerXmlPath);
        }

        return $xml;
    }
}

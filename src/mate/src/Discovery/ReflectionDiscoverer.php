<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Discovery;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Attribute\AsResource;
use Symfony\AI\Mate\Attribute\AsResourceTemplate;
use Symfony\AI\Mate\Attribute\AsTool;
use Symfony\AI\Mate\Discovery\Model\DiscoveredCapabilities;
use Symfony\AI\Mate\Discovery\Model\ResourceDefinition;
use Symfony\AI\Mate\Discovery\Model\ResourceTemplateDefinition;
use Symfony\AI\Mate\Discovery\Model\ToolDefinition;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Discovers Mate capabilities by scanning directories for classes whose methods carry
 * `#[AsTool]`, `#[AsResource]`, or `#[AsResourceTemplate]` attributes.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ReflectionDiscoverer
{
    private LoggerInterface $logger;
    private SchemaGenerator $schemaGenerator;
    private DocBlockParser $docBlockParser;

    public function __construct(
        ?LoggerInterface $logger = null,
        ?DocBlockParser $docBlockParser = null,
        ?SchemaGenerator $schemaGenerator = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->docBlockParser = $docBlockParser ?? new DocBlockParser();
        $this->schemaGenerator = $schemaGenerator ?? new SchemaGenerator($this->docBlockParser);
    }

    /**
     * @param string[] $directories directories relative to the base path to scan
     */
    public function discover(string $basePath, array $directories): DiscoveredCapabilities
    {
        $absolutePaths = [];
        foreach ($directories as $dir) {
            $path = rtrim($basePath, '/').'/'.ltrim($dir, '/');
            if (is_dir($path)) {
                $absolutePaths[] = $path;
            }
        }

        if ([] === $absolutePaths) {
            return new DiscoveredCapabilities();
        }

        $tools = [];
        $resources = [];
        $resourceTemplates = [];

        try {
            $finder = new Finder();
            $finder->files()->in($absolutePaths)->name('*.php');

            foreach ($finder as $file) {
                $this->processFile($file, $tools, $resources, $resourceTemplates);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error during Mate capability discovery', ['exception' => $e]);
        }

        return new DiscoveredCapabilities($tools, $resources, $resourceTemplates);
    }

    /**
     * @param array<string, ToolDefinition>             $tools
     * @param array<string, ResourceDefinition>         $resources
     * @param array<string, ResourceTemplateDefinition> $resourceTemplates
     */
    private function processFile(SplFileInfo $file, array &$tools, array &$resources, array &$resourceTemplates): void
    {
        $className = $this->getClassFromFile($file);
        if (null === $className) {
            return;
        }

        // $className is guaranteed to be an existing class by getClassFromFile().
        $reflectionClass = new \ReflectionClass($className);

        if ($reflectionClass->isAbstract() || $reflectionClass->isInterface() || $reflectionClass->isTrait() || $reflectionClass->isEnum()) {
            return;
        }

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isAbstract() || $method->isConstructor() || $method->isDestructor()) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() !== $reflectionClass->getName()) {
                continue;
            }

            $this->processMethod($method, $tools, $resources, $resourceTemplates);
        }
    }

    /**
     * @param array<string, ToolDefinition>             $tools
     * @param array<string, ResourceDefinition>         $resources
     * @param array<string, ResourceTemplateDefinition> $resourceTemplates
     */
    private function processMethod(\ReflectionMethod $method, array &$tools, array &$resources, array &$resourceTemplates): void
    {
        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        $toolAttribute = $method->getAttributes(AsTool::class)[0] ?? null;
        if (null !== $toolAttribute) {
            $instance = $toolAttribute->newInstance();
            $description = $instance->description ?? $this->docBlockParser->getSummary($method->getDocComment());
            $tools[$instance->name] = new ToolDefinition(
                $instance->name,
                $instance->title,
                $description,
                $this->schemaGenerator->generate($method),
                $className,
                $methodName,
            );

            return;
        }

        $resourceAttribute = $method->getAttributes(AsResource::class)[0] ?? null;
        if (null !== $resourceAttribute) {
            $instance = $resourceAttribute->newInstance();
            $description = $instance->description ?? $this->docBlockParser->getSummary($method->getDocComment());
            $resources[$instance->uri] = new ResourceDefinition(
                $instance->uri,
                $instance->name,
                $instance->title,
                $description,
                $instance->mimeType,
                $className,
                $methodName,
            );

            return;
        }

        $templateAttribute = $method->getAttributes(AsResourceTemplate::class)[0] ?? null;
        if (null !== $templateAttribute) {
            $instance = $templateAttribute->newInstance();
            $description = $instance->description ?? $this->docBlockParser->getSummary($method->getDocComment());
            $resourceTemplates[$instance->uriTemplate] = new ResourceTemplateDefinition(
                $instance->uriTemplate,
                $instance->name,
                $instance->title,
                $description,
                $instance->mimeType,
                $className,
                $methodName,
            );
        }
    }

    /**
     * Determines the fully-qualified class name declared in a PHP file via tokenization.
     *
     * @return class-string|null
     */
    private function getClassFromFile(SplFileInfo $file): ?string
    {
        try {
            $content = $file->getContents();
        } catch (\Throwable $e) {
            $this->logger->debug('Could not read file during discovery', ['file' => $file->getPathname(), 'exception' => $e]);

            return null;
        }

        if (\strlen($content) > 512 * 1024) {
            return null;
        }

        $tokens = token_get_all($content);
        $namespace = '';
        $tokenCount = \count($tokens);

        for ($i = 0; $i < $tokenCount; ++$i) {
            if (\is_array($tokens[$i]) && \T_NAMESPACE === $tokens[$i][0]) {
                for ($j = $i + 1; $j < $tokenCount; ++$j) {
                    if (';' === $tokens[$j] || '{' === $tokens[$j]) {
                        break 2;
                    }
                    if (\is_array($tokens[$j]) && \in_array($tokens[$j][0], [\T_STRING, \T_NAME_QUALIFIED], true)) {
                        $namespace .= $tokens[$j][1];
                    } elseif (\is_array($tokens[$j]) && \T_NS_SEPARATOR === $tokens[$j][0]) {
                        $namespace .= '\\';
                    }
                }
            }
        }
        $namespace = trim($namespace, '\\');

        for ($i = 0; $i < $tokenCount; ++$i) {
            if (\is_array($tokens[$i]) && \T_CLASS === $tokens[$i][0]) {
                for ($j = $i + 1; $j < $tokenCount; ++$j) {
                    if (\is_array($tokens[$j]) && \T_STRING === $tokens[$j][0]) {
                        $className = '' !== $namespace ? $namespace.'\\'.$tokens[$j][1] : $tokens[$j][1];

                        /* @var class-string $className */
                        return class_exists($className) ? $className : null;
                    }
                    if ('{' === $tokens[$j] || '(' === $tokens[$j]) {
                        break;
                    }
                }
            }
        }

        return null;
    }
}

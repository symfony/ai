<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Profiler;

use Mcp\Capability\Discovery\DiscoveryState;
use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\Registry\ResourceReference;
use Mcp\Capability\Registry\ResourceTemplateReference;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\Resource;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decorator for Registry that provides access to capabilities for the profiler.
 *
 * @author Camille Islasse <guiziweb@gmail.com>
 */
final class TraceableRegistry implements RegistryInterface, ResetInterface
{
    public function __construct(
        private readonly RegistryInterface $registry,
    ) {
    }

    public function registerTool(Tool $tool, callable|array|string $handler, bool $isManual = false): void
    {
        $this->registry->registerTool($tool, $handler, $isManual);
    }

    public function registerResource(Resource $resource, callable|array|string $handler, bool $isManual = false): void
    {
        $this->registry->registerResource($resource, $handler, $isManual);
    }

    public function registerResourceTemplate(
        ResourceTemplate $template,
        callable|array|string $handler,
        array $completionProviders = [],
        bool $isManual = false,
    ): void {
        $this->registry->registerResourceTemplate($template, $handler, $completionProviders, $isManual);
    }

    public function registerPrompt(
        Prompt $prompt,
        callable|array|string $handler,
        array $completionProviders = [],
        bool $isManual = false,
    ): void {
        $this->registry->registerPrompt($prompt, $handler, $completionProviders, $isManual);
    }

    public function clear(): void
    {
        $this->registry->clear();
    }

    public function getDiscoveryState(): DiscoveryState
    {
        return $this->registry->getDiscoveryState();
    }

    public function setDiscoveryState(DiscoveryState $state): void
    {
        $this->registry->setDiscoveryState($state);
    }

    public function hasTools(): bool
    {
        return $this->registry->hasTools();
    }

    public function getTools(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->registry->getTools($limit, $cursor);
    }

    public function getTool(string $name): ToolReference
    {
        return $this->registry->getTool($name);
    }

    public function hasResources(): bool
    {
        return $this->registry->hasResources();
    }

    public function getResources(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->registry->getResources($limit, $cursor);
    }

    public function getResource(string $uri, bool $includeTemplates = true): ResourceReference|ResourceTemplateReference
    {
        return $this->registry->getResource($uri, $includeTemplates);
    }

    public function hasResourceTemplates(): bool
    {
        return $this->registry->hasResourceTemplates();
    }

    public function getResourceTemplates(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->registry->getResourceTemplates($limit, $cursor);
    }

    public function getResourceTemplate(string $uriTemplate): ResourceTemplateReference
    {
        return $this->registry->getResourceTemplate($uriTemplate);
    }

    public function hasPrompts(): bool
    {
        return $this->registry->hasPrompts();
    }

    public function getPrompts(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->registry->getPrompts($limit, $cursor);
    }

    public function getPrompt(string $name): PromptReference
    {
        return $this->registry->getPrompt($name);
    }

    public function reset(): void
    {
        $this->clear();
    }
}

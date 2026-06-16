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

use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\Registry\ResourceReference;
use Mcp\Capability\Registry\ResourceTemplateReference;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;

/**
 * Decorator for Registry that provides access to capabilities for the profiler.
 *
 * @author Camille Islasse <guiziweb@gmail.com>
 */
final class TraceableRegistry implements RegistryInterface
{
    public function __construct(
        private readonly RegistryInterface $registry,
    ) {
    }

    public function registerTool(Tool $tool, callable|array|string $handler): ToolReference
    {
        return $this->registry->registerTool($tool, $handler);
    }

    public function registerResource(ResourceDefinition $resource, callable|array|string $handler): ResourceReference
    {
        return $this->registry->registerResource($resource, $handler);
    }

    public function registerResourceTemplate(
        ResourceTemplate $template,
        callable|array|string $handler,
        array $completionProviders = [],
    ): ResourceTemplateReference {
        return $this->registry->registerResourceTemplate($template, $handler, $completionProviders);
    }

    public function registerPrompt(
        Prompt $prompt,
        callable|array|string $handler,
        array $completionProviders = [],
    ): PromptReference {
        return $this->registry->registerPrompt($prompt, $handler, $completionProviders);
    }

    public function unregisterTool(string $name): void
    {
        $this->registry->unregisterTool($name);
    }

    public function unregisterResource(string $uri): void
    {
        $this->registry->unregisterResource($uri);
    }

    public function unregisterResourceTemplate(string $uriTemplate): void
    {
        $this->registry->unregisterResourceTemplate($uriTemplate);
    }

    public function unregisterPrompt(string $name): void
    {
        $this->registry->unregisterPrompt($name);
    }

    public function hasTool(string $name): bool
    {
        return $this->registry->hasTool($name);
    }

    public function hasResource(string $uri): bool
    {
        return $this->registry->hasResource($uri);
    }

    public function hasResourceTemplate(string $uriTemplate): bool
    {
        return $this->registry->hasResourceTemplate($uriTemplate);
    }

    public function hasPrompt(string $name): bool
    {
        return $this->registry->hasPrompt($name);
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
}

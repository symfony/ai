<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Discovery\Model;

/**
 * Immutable bag of capabilities discovered in a set of directories.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DiscoveredCapabilities
{
    /**
     * @param array<string, ToolDefinition>             $tools             keyed by tool name
     * @param array<string, ResourceDefinition>         $resources         keyed by resource URI
     * @param array<string, ResourceTemplateDefinition> $resourceTemplates keyed by URI template
     */
    public function __construct(
        private array $tools = [],
        private array $resources = [],
        private array $resourceTemplates = [],
    ) {
    }

    /**
     * @return array<string, ToolDefinition>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * @return array<string, ResourceDefinition>
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * @return array<string, ResourceTemplateDefinition>
     */
    public function getResourceTemplates(): array
    {
        return $this->resourceTemplates;
    }

    public function merge(self $other): self
    {
        return new self(
            array_merge($this->tools, $other->tools),
            array_merge($this->resources, $other->resources),
            array_merge($this->resourceTemplates, $other->resourceTemplates),
        );
    }
}

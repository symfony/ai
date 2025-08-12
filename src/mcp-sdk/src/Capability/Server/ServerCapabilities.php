<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Server;

use Symfony\AI\McpSdk\Capability\Completion\CompletionCapability;
use Symfony\AI\McpSdk\Capability\Logging\LoggingCapability;
use Symfony\AI\McpSdk\Capability\Prompt\PromptCapability;
use Symfony\AI\McpSdk\Capability\Resource\ResourceCapability;
use Symfony\AI\McpSdk\Capability\Tool\ToolCapability;

/**
 * Capabilities that a server may support. Known capabilities are defined here, in this schema,
 * but this is not a closed set: any server can define its own, additional capabilities.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities
 */
final readonly class ServerCapabilities implements \JsonSerializable
{
    /**
     * @param array<string, array<string, mixed>>|null $experimental
     */
    public function __construct(
        /**
         * Present if the server supports sending log messages to the client.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-logging
         */
        public ?LoggingCapability $logging = null,
        /**
         * Present if the server offers any prompt templates.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-prompts
         */
        public ?PromptCapability $prompts = null,
        /**
         * Present if the server offers any resources to read.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-resources
         */
        public ?ResourceCapability $resources = null,
        /**
         * Present if the server offers any tools to call.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-tools
         */
        public ?ToolCapability $tools = null,
        /**
         * Present if the server supports argument autocompletion suggestions.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-completions
         */
        public ?CompletionCapability $completions = null,
        /**
         * @var array<string, array<string, mixed>>|null
         */
        public ?array $experimental = null,
    ) {
    }

    /**
     * @return array{
     *     logging?: LoggingCapability,
     *     prompts?: PromptCapability,
     *     resources?: ResourceCapability,
     *     tools?: ToolCapability,
     *     completions?: CompletionCapability,
     *     experimental?: array<string, array<string, mixed>>
     * }
     */
    public function jsonSerialize(): array
    {
        return array_filter((array) $this, fn ($value) => null !== $value);
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App;

use Symfony\AI\McpSdk\Capability\Prompt\PromptCapability;
use Symfony\AI\McpSdk\Capability\PromptChain;
use Symfony\AI\McpSdk\Capability\Resource\ResourceCapability;
use Symfony\AI\McpSdk\Capability\ResourceChain;
use Symfony\AI\McpSdk\Capability\Server\Implementation;
use Symfony\AI\McpSdk\Capability\Server\ProtocolVersion;
use Symfony\AI\McpSdk\Capability\Server\ServerCapabilities;
use Symfony\AI\McpSdk\Capability\Tool\ToolCapability;
use Symfony\AI\McpSdk\Capability\ToolChain;
use Symfony\AI\McpSdk\Server\NotificationHandler\InitializedHandler;
use Symfony\AI\McpSdk\Server\NotificationHandlerInterface;
use Symfony\AI\McpSdk\Server\RequestHandler\InitializeHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\PingHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\PromptGetHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\PromptListHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ResourceListHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ResourceReadHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolCallHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolListHandler;
use Symfony\AI\McpSdk\Server\RequestHandlerInterface;

class Builder
{
    /**
     * @return list<RequestHandlerInterface>
     */
    public static function buildRequestHandlers(): array
    {
        $promptManager = new PromptChain([
            new ExamplePrompt(),
        ]);

        $resourceManager = new ResourceChain([
            new ExampleResource(),
        ]);

        $toolManager = new ToolChain([
            new ExampleTool(),
        ]);

        $implementation = new Implementation(
            name: 'MCP-SDK-CLI-Example',
            version: '0.1.0'
        );
        $serverCapabilities = new ServerCapabilities(
            prompts: new PromptCapability(listChanged: false),
            resources: new ResourceCapability(subscribe: false, listChanged: false),
            tools: new ToolCapability(listChanged: false),
        );

        return [
            new InitializeHandler(
                implementation: $implementation,
                serverCapabilities: $serverCapabilities,
                protocolVersion: ProtocolVersion::V2025_03_26,
                instructions: 'Optional LLM instructions/hints',
            ),
            new PingHandler(),
            new PromptListHandler($promptManager),
            new PromptGetHandler($promptManager),
            new ResourceListHandler($resourceManager),
            new ResourceReadHandler($resourceManager),
            new ToolCallHandler($toolManager),
            new ToolListHandler($toolManager),
        ];
    }

    /**
     * @return list<NotificationHandlerInterface>
     */
    public static function buildNotificationHandlers(): array
    {
        return [
            new InitializedHandler(),
        ];
    }
}

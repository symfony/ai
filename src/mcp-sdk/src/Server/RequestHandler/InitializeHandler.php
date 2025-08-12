<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server\RequestHandler;

use Symfony\AI\McpSdk\Capability\Server\Implementation;
use Symfony\AI\McpSdk\Capability\Server\ProtocolVersionEnum;
use Symfony\AI\McpSdk\Capability\Server\ServerCapabilities;
use Symfony\AI\McpSdk\Field\MetaField;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;

final class InitializeHandler extends BaseRequestHandler
{
    public function __construct(
        /**
         * Describes the name and version of an MCP implementation, with an optional title for UI representation.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#implementation
         */
        private readonly Implementation $implementation,
        /**
         * Capabilities that a server may support. Known capabilities are defined here, in this schema,
         * but this is not a closed set: any server can define its own, additional capabilities.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities
         */
        private readonly ServerCapabilities $serverCapabilities,
        /**
         * The version of the Model Context Protocol that the server wants to use.
         * This may not match the version that the client requested.
         * If the client cannot support this version, it MUST disconnect.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#initializeresult-protocolversion
         */
        private readonly ProtocolVersionEnum $protocolVersion = ProtocolVersionEnum::V2025_03_26,
        /**
         * Instructions describing how to use the server and its features.
         *
         * This can be used by clients to improve the LLM’s understanding of available tools, resources, etc.
         * It can be thought of like a “hint” to the model.
         * For example, this information MAY be added to the system prompt.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#initializeresult-instructions
         */
        private readonly ?string $instructions = null,
        /**
         * The _meta property/parameter is reserved by MCP to allow clients and servers to attach additional metadata to their interactions.
         *
         * @see https://modelcontextprotocol.io/specification/2025-06-18/basic/index#general-fields
         */
        private readonly ?MetaField $metaField = null,
    ) {
    }

    /**
     * After receiving an initialize request from the client, the server sends this response.
     *
     * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#initializeresult.
     */
    public function createResponse(Request $message): Response
    {
        return new Response($message->id, [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->serverCapabilities->jsonSerialize(),
            'serverInfo' => $this->implementation,
            'instructions' => $this->instructions,
            '_meta' => $this->metaField,
        ]);
    }

    protected function supportedMethod(): string
    {
        return 'initialize';
    }
}

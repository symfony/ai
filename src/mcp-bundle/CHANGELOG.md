CHANGELOG
=========

0.1
---

 * Add Symfony bundle bridging MCP-SDK with Symfony applications
 * Add server mode exposing Symfony tools to MCP clients:
   - STDIO transport via `php bin/console mcp` command
   - SSE (Server-Sent Events) transport via HTTP endpoints
   - Automatic tool discovery and registration
   - Integration with AI-Bundle tools
 * Add routing configuration for SSE endpoints:
   - `/_mcp/sse` for SSE connections
   - `/_mcp/messages/{id}` for message retrieval
 * Add `McpController` for handling SSE connections
 * Add `McpCommand` providing STDIO interface
 * Add bundle configuration for enabling/disabling transports
 * Add cache-based SSE message storage
 * Add service configuration for MCP server setup
 * Classes extending `\Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface` automatically
   get the `mcp.tool` tag for MCP tool discovery
 * Add composer script for PHPStan static analysis workflow

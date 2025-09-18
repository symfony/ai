CHANGELOG
=========

0.2
---

 * [BC BREAK] Migration from `symfony/mcp-sdk` to official `mcp/sdk`
 * [BC BREAK] Tool definition changed from interfaces to PHP attributes `#[McpTool]`
 * [BC BREAK] Automatic tool discovery via attributes instead of manual registration
 * [BC BREAK] Server creation now uses `Server::make()` builder pattern
 * Updated STDIO transport command to `mcp:server`
 * Simplified service configuration using native SDK patterns
 * Enhanced auto-discovery scanning in `src/` directory

0.1
---

 * Add Symfony bundle bridging MCP-SDK with Symfony applications
 * Add server mode exposing Symfony tools to MCP clients:
   - STDIO transport via `php bin/console mcp:server` command
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
 * Tools using `#[McpTool]` attribute automatically discovered

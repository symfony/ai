CHANGELOG
=========

0.1
---

 * Add Symfony bundle providing Model Context Protocol integration using official `mcp/sdk`
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
 * Prompts using `#[McpPrompt]` attribute automatically discovered
 * Resources using `#[McpResource]` attribute automatically discovered
 * Resource templates using `#[McpResourceTemplate]` attribute automatically discovered

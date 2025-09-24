CHANGELOG
=========

0.1
---

 * Add Symfony bundle providing Model Context Protocol integration using official `mcp/sdk`
 * Add server mode exposing MCP capabilities to clients:
   - STDIO transport via `php bin/console mcp:server` command
   - HTTP transport via StreamableHttpTransport using configurable endpoints
   - Automatic capability discovery and registration
   - EventDispatcher integration for capability change notifications
 * Add configurable HTTP transport features:
   - Configurable endpoint path (default: `/_mcp`)
   - File and memory session store options
   - TTL configuration for session management
   - CORS headers for cross-origin requests
 * Add `McpController` for handling HTTP transport connections
 * Add `McpCommand` providing STDIO interface
 * Add bundle configuration for transport selection and HTTP options
 * Add dedicated MCP logger with configurable Monolog integration
 * Add pagination and instructions configuration
 * Tools using `#[McpTool]` attribute automatically discovered
 * Prompts using `#[McpPrompt]` attribute automatically discovered
 * Resources using `#[McpResource]` attribute automatically discovered
 * Resource templates using `#[McpResourceTemplate]` attribute automatically discovered

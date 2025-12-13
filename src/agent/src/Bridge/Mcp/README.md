MCP Bridge for Symfony AI
=========================

This bridge provides MCP (Model Context Protocol) client support for Symfony AI,
allowing you to connect to MCP servers and use their tools within your AI agents.

> **Note:** The [official MCP PHP SDK](https://github.com/modelcontextprotocol/php-sdk) is planning
> to add a client component. Once available, this bridge may switch to using the official client
> instead of its own transport implementation.

Installation
------------

```bash
composer require symfony/ai-mcp-tool
```

Usage
-----

### With Symfony AI Bundle

Configure MCP servers in your `config/packages/ai.yaml`:

```yaml
ai:
    mcp:
        my_server:
            transport: stdio
            command: npx
            args: ['@modelcontextprotocol/server-filesystem', '/tmp']

        remote_server:
            transport: sse
            url: 'https://example.com/sse'
            headers:
                Authorization: 'Bearer %env(MCP_API_KEY)%'
```

### Standalone Usage

```php
use Symfony\AI\Agent\Bridge\Mcp\McpClient;
use Symfony\AI\Agent\Bridge\Mcp\McpToolbox;
use Symfony\AI\Agent\Bridge\Mcp\Transport\StdioTransport;

// Create transport
$transport = new StdioTransport('npx', ['@modelcontextprotocol/server-filesystem', '/tmp']);

// Create client and toolbox
$client = new McpClient($transport);
$toolbox = new McpToolbox($client);

// Get available tools
$tools = $toolbox->getTools();

// Use with an agent
$agent = new Agent($platform, $toolbox);
```

Transports
----------

### StdioTransport

For local MCP servers that communicate via stdin/stdout:

```php
$transport = new StdioTransport(
    command: 'npx',
    args: ['@modelcontextprotocol/server-filesystem', '/tmp'],
    env: ['NODE_ENV' => 'production'],
    timeout: 30
);
```

### HttpTransport

For remote MCP servers using the Streamable HTTP protocol:

```php
$transport = new HttpTransport(
    httpClient: $httpClient,
    url: 'https://example.com/mcp',
    headers: ['Authorization' => 'Bearer token']
);
```

### SseTransport

For remote MCP servers using Server-Sent Events:

```php
$transport = new SseTransport(
    httpClient: $httpClient,
    url: 'https://example.com/sse',
    headers: ['Authorization' => 'Bearer token']
);
```

Resources
---------

* [MCP Specification](https://modelcontextprotocol.io/)
* [Symfony AI Documentation](https://symfony.com/doc/current/ai.html)
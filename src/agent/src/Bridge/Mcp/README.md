MCP AI Tool
===========

Exposes the tools advertised by a remote [Model Context Protocol](https://modelcontextprotocol.io)
server to a Symfony AI `Agent`.

An agent is not an MCP client: it never asks a server for a prompt or reads one of
its resources, it only draws tools from it. The bridge therefore models a *toolset*
behind an MCP connection rather than the server itself. A server answering
`tools/list` and `tools/call` already is a toolbox, so it becomes one: `McpToolbox`
turns the server's tool list into `Symfony\AI\Platform\Tool\Tool` definitions and
forwards every call, without reflecting on them the way local `#[AsTool]` services
are handled. Tool names are prefixed with the server's short name — a `read_file`
tool on a server named `filesystem` becomes `filesystem_read_file` — so several
servers can be attached to the same agent without their names colliding.

Standalone, a toolset is reached through the official `mcp/sdk` client:

```php
use Mcp\Client;
use Mcp\Client\Transport\StdioTransport;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Bridge\Mcp\ClientToolset;
use Symfony\AI\Agent\Bridge\Mcp\McpToolbox;

$toolset = new ClientToolset('filesystem', Client::builder()->build(), new StdioTransport('npx', ['-y', '@modelcontextprotocol/server-filesystem', __DIR__]));

$agent = new Agent($platform, 'gpt-4o-mini', toolbox: new McpToolbox($toolset));
```

In a Symfony application, configure the connection once with the MCP bundle's
`mcp.clients:` option and point an agent at it with an `mcp_server:` entry in the AI
bundle's `tools:` list instead — the bundles adapt the already-configured connection
rather than opening a second one.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)

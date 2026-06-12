.. card:
    title: Build an MCP Server
    description: Expose tools, prompts, and resources to AI assistants over MCP.
    icon: server
    components: MCP Bundle

Build an MCP Server
===================

The Model Context Protocol (MCP) lets AI assistants like Claude Desktop discover and call tools
exposed by your application. In this guide you will install the MCP Bundle, create a tool, a
prompt, and a resource, then test them with a client.

Prerequisites
-------------

* A Symfony application
* Symfony AI MCP Bundle

Step 1: Install the MCP Bundle
------------------------------

The MCP Bundle integrates the official ``mcp/sdk`` PHP package into your Symfony application::

    composer require symfony/mcp-bundle

Step 2: Configure Routing
-------------------------

Add the MCP route to your routing configuration. This exposes the HTTP endpoint that MCP clients
connect to:

.. code-block:: yaml

    # config/routes.yaml
    mcp:
        resource: .
        type: mcp

Step 3: Create a Tool
---------------------

Use the ``#[McpTool]`` attribute on a method to expose it as a callable tool. MCP clients see the
tool name and can invoke it with parameters::

    namespace App\Mcp;

    use Mcp\Capability\Attribute\McpTool;

    class CurrentTimeTool
    {
        #[McpTool(name: 'current-time')]
        public function getCurrentTime(string $format = 'Y-m-d H:i:s'): string
        {
            return (new \DateTime('now', new \DateTimeZone('UTC')))->format($format);
        }
    }

.. tip::

    You can place multiple ``#[McpTool]`` methods in the same class to group related tools
    together.

Step 4: Configure Transport
---------------------------

Enable STDIO and/or HTTP transport in the bundle configuration. The STDIO transport is used by
command-line clients; HTTP is used by web-based clients and the MCP Inspector:

.. code-block:: yaml

    # config/packages/mcp.yaml
    mcp:
        app: 'my-app'
        version: '1.0.0'
        description: 'My Symfony MCP server'

        client_transports:
            stdio: true
            http: true

        http:
            path: /_mcp

Step 5: Create a Prompt
-----------------------

Prompts provide system instructions that AI clients can request. Use the ``#[McpPrompt]``
attribute and return an array of messages::

    namespace App\Mcp;

    use Mcp\Capability\Attribute\McpPrompt;

    class TimePrompts
    {
        #[McpPrompt(name: 'time-analysis')]
        public function getTimeAnalysisPrompt(): array
        {
            return [
                ['role' => 'user', 'content' => 'You are a time management expert.'],
            ];
        }
    }

Step 6: Create a Resource
-------------------------

Resources expose static data that AI clients can read. Use the ``#[McpResource]`` attribute with
a URI and a name::

    namespace App\Mcp;

    use Mcp\Capability\Attribute\McpResource;

    class TimeResource
    {
        #[McpResource(uri: 'time://current', name: 'current-time')]
        public function getCurrentTimeResource(): array
        {
            return [
                'uri' => 'time://current',
                'mimeType' => 'text/plain',
                'text' => (new \DateTime('now'))->format('Y-m-d H:i:s'),
            ];
        }
    }

Step 7: Test with an MCP Client
-------------------------------

To connect Claude Desktop (or any MCP-compatible client) to your server, add an entry to the
client's configuration file pointing at your Symfony console command for STDIO transport:

.. code-block:: json

    {
        "mcpServers": {
            "my-app": {
                "command": "php /path/to/project/bin/console mcp:server"
            }
        }
    }

For the HTTP transport, point the client at your application's MCP endpoint instead:

.. code-block:: json

    {
        "mcpServers": {
            "my-app": {
                "url": "http://localhost:8000/_mcp"
            }
        }
    }

.. tip::

    Run ``symfony console mcp:server`` to start the STDIO server manually. This is useful for
    debugging before connecting a client.

Learn More
----------

* :doc:`../bundles/mcp-bundle` - Full configuration, session storage, and event system
* `Model Context Protocol Specification <https://modelcontextprotocol.io/>`_ - The official MCP standard
* `MCP PHP SDK <https://php.sdk.modelcontextprotocol.io/>`_ - Documentation for the official MCP SDK for PHP

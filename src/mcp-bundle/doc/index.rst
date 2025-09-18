MCP Bundle
==========

Symfony integration bundle for `Model Context Protocol`_ using the official MCP SDK `mcp/sdk`_.

**Currently only supports tools as server via Server-Sent Events (SSE) and STDIO.**

Installation
------------

.. code-block:: terminal

    $ composer require symfony/mcp-bundle

Usage
-----

At first, you need to decide whether your application should act as a MCP server or client. Both can be configured in
the ``mcp`` section of your ``config/packages/mcp.yaml`` file.

**Act as Server**

.. warning::

    Currently only supports tools. Support for prompts, resources, and other features coming soon.

To use your application as an MCP server, exposing tools to clients like `Claude Desktop`_, you need to configure in the
``client_transports`` section the transports you want to expose to clients. You can use either STDIO or SSE.

**Creating Tools**

Tools are automatically discovered using PHP attributes. Create a class with methods annotated with ``#[McpTool]``:

.. code-block:: php

    <?php

    use Mcp\Capability\Attribute\McpTool;

    class CurrentTimeTool
    {
        #[McpTool(name: 'current-time')]
        public function getCurrentTime(string $format = 'Y-m-d H:i:s'): string
        {
            return (new \DateTime('now', new \DateTimeZone('UTC')))->format($format);
        }
    }

Tools are automatically discovered in the ``src/`` directory when the server starts.

**Act as Client**

.. warning::

    Not implemented yet, but planned for the future.

To use your application as an MCP client, integrating other MCP servers, you need to configure the ``servers`` you want
to connect to. You can use either  STDIO or Server-Sent Events (SSE) as transport methods.

You can find a list of example Servers in the `MCP Server List`_.

Tools of those servers are available in your `AI Bundle`_ configuration and usable in your agents.

Configuration
-------------

.. code-block:: yaml

    # config/packages/mcp.yaml
    mcp:
        app: 'app' # Application name to be exposed to clients
        version: '1.0.0' # Application version to be exposed to clients

        client_transports:
            stdio: true # Enable STDIO via command
            sse: true # Enable Server-Sent Event via controller

        servers:
            name:
                transport: 'stdio' # Transport method to use, either 'stdio' or 'sse'
                stdio:
                    command: 'php /path/bin/console mcp:server' # Command to execute to start the client
                    arguments: [] # Arguments to pass to the command
                sse:
                    url: 'http://localhost:8000/sse' # URL to SSE endpoint of MCP server

.. _`Model Context Protocol`: https://modelcontextprotocol.io/
.. _`mcp/sdk`: https://github.com/modelcontextprotocol/php-sdk
.. _`Claude Desktop`: https://claude.ai/download
.. _`MCP Server List`: https://modelcontextprotocol.io/examples
.. _`AI Bundle`: https://github.com/symfony/ai-bundle

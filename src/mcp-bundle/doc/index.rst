MCP Bundle
==========

Symfony integration bundle for `Model Context Protocol`_ using the official MCP SDK `mcp/sdk`_.

**Supports MCP capabilities (tools, prompts, resources) as server via Server-Sent Events (SSE) and STDIO. Resource templates implementation ready but awaiting MCP SDK support.**

Installation
------------

.. code-block:: terminal

    $ composer require symfony/mcp-bundle

Usage
-----

At first, you need to decide whether your application should act as a MCP server or client. Both can be configured in
the ``mcp`` section of your ``config/packages/mcp.yaml`` file.

**Act as Server**

To use your application as an MCP server, exposing tools, prompts, resources, and resource templates to clients like `Claude Desktop`_, you need to configure in the
``client_transports`` section the transports you want to expose to clients. You can use either STDIO or SSE.

**Creating MCP Capabilities**

MCP capabilities are automatically discovered using PHP attributes.

**Tools** - Actions that can be executed::

    use Mcp\Capability\Attribute\McpTool;

    class CurrentTimeTool
    {
        #[McpTool(name: 'current-time')]
        public function getCurrentTime(string $format = 'Y-m-d H:i:s'): string
        {
            return (new \DateTime('now', new \DateTimeZone('UTC')))->format($format);
        }
    }

**Prompts** - System instructions for AI context::

    use Mcp\Capability\Attribute\McpPrompt;

    class TimePrompts
    {
        #[McpPrompt(name: 'time-analysis')]
        public function getTimeAnalysisPrompt(): array
        {
            return [
                ['role' => 'user', 'content' => 'You are a time management expert.']
            ];
        }
    }

**Resources** - Static data that can be read::

    use Mcp\Capability\Attribute\McpResource;

    class TimeResource
    {
        #[McpResource(uri: 'time://current', name: 'current-time')]
        public function getCurrentTimeResource(): array
        {
            return [
                'uri' => 'time://current',
                'mimeType' => 'text/plain',
                'text' => (new \DateTime('now'))->format('Y-m-d H:i:s')
            ];
        }
    }

**Resource Templates** - Dynamic resources with parameters:

.. note::

    Resource Templates are not yet functional as the underlying MCP SDK is missing the required handlers.
    See `MCP SDK issue #9 <https://github.com/modelcontextprotocol/php-sdk/issues/9>`_ for implementation status.

::

    use Mcp\Capability\Attribute\McpResourceTemplate;

    class TimeResourceTemplate
    {
        #[McpResourceTemplate(uriTemplate: 'time://{timezone}', name: 'time-by-timezone')]
        public function getTimeByTimezone(string $timezone): array
        {
            $time = (new \DateTime('now', new \DateTimeZone($timezone)))->format('Y-m-d H:i:s T');
            return [
                'uri' => "time://$timezone",
                'mimeType' => 'text/plain',
                'text' => $time
            ];
        }
    }

All capabilities are automatically discovered in the ``src/`` directory when the server starts.

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
        pagination_limit: 50 # Maximum number of items returned per list request (default: 50)
        instructions: | # Instructions describing how to use the server (for LLMs)
            This demo MCP server provides time management capabilities.

            Available tools:
            - current-time: Get the current timestamp

            Available resources:
            - time://current: Current time resource

            Available prompts:
            - time-analysis: Expert time management analysis

        client_transports:
            stdio: true # Enable STDIO via command
            sse: true # Enable Server-Sent Event via controller

        servers:
            name:
                transport: 'stdio' # Transport method to use, either 'stdio' or 'sse'
                stdio:
                    command: 'php /path/bin/console mcp:server' # Command to execute to start the server
                    arguments: [] # Arguments to pass to the command
                sse:
                    url: 'http://localhost:8000/sse' # URL to SSE endpoint of MCP server

Logging Configuration
---------------------

By default, MCP uses a dedicated logger channel that inherits your application's default logging configuration.
To configure MCP-specific logging, add the following to your ``config/packages/monolog.yaml``:

.. code-block:: yaml

    # config/packages/monolog.yaml
    monolog:
        channels: ['mcp']
        handlers:
            mcp:
                type: rotating_file
                path: '%kernel.logs_dir%/mcp.log'
                level: info
                channels: ['mcp']
                max_files: 30

You can customize the logging level and destination according to your needs:

.. code-block:: yaml

    # Example: Different levels per environment
    monolog:
        handlers:
            mcp_dev:
                type: stream
                path: '%kernel.logs_dir%/mcp.log'
                level: debug
                channels: ['mcp']
            mcp_prod:
                type: slack
                level: error
                channels: ['mcp']
                webhook_url: '%env(SLACK_WEBHOOK)%'

.. _`Model Context Protocol`: https://modelcontextprotocol.io/
.. _`mcp/sdk`: https://github.com/modelcontextprotocol/php-sdk
.. _`Claude Desktop`: https://claude.ai/download
.. _`MCP Server List`: https://modelcontextprotocol.io/examples
.. _`AI Bundle`: https://github.com/symfony/ai-bundle

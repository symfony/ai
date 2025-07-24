MCP Bundle
==========

Symfony integration bundle for `Model Context Protocol`_ using the Symfony AI MCP SDK `symfony/mcp-sdk`_.

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
                    command: 'php /path/bin/console mcp' # Command to execute to start the client
                    arguments: [] # Arguments to pass to the command
                sse:
                    url: 'http://localhost:8000/sse' # URL to SSE endpoint of MCP server

Usage as a remote MCP server
----------------------------

If your local project runs in docker, it may be easier to run the server as a remote server, thanks to the node package ``mcp-remote``. Node is required.

Configuration for claude desktop:

.. code-block:: json

    {
      "mcpServers": {
        "symfony": {
          "command": "npx",
          "args": [
            "mcp-remote",
            "https://127.0.0.1:8080"
          ]
        }
      }
    }

Quick tips
..........

Self Signed certificates
~~~~~~~~~~~~~~~~~~~~~~~~
Add the following section:

.. code-block:: json

    "env": {
      "NODE_EXTRA_CA_CERTS": "full/path/to/cert_root.pem"
    }

Allow http
~~~~~~~~~~
Add the argument ``--allow-http``:

.. code-block:: json

    "args": [
      "mcp-remote",
      "https://127.0.0.1:8080",
      "--allow-http"
    ]


.. _`Model Context Protocol`: https://modelcontextprotocol.io/
.. _`symfony/mcp-sdk`: https://github.com/symfony/mcp-sdk
.. _`Claude Desktop`: https://claude.ai/download
.. _`MCP Server List`: https://modelcontextprotocol.io/examples
.. _`AI Bundle`: https://github.com/symfony/ai-bundle

OpenAI Remote MCP Tools & Connectors
====================================

OpenAI's Responses API supports remote MCP (Model Context Protocol) servers and built-in connectors as tools.
This allows the model to discover and call tools hosted on external MCP servers or use pre-built connectors
for services like Gmail, Google Drive, and others — all without custom tool implementations.

.. seealso::

    `OpenAI MCP Tools & Connectors documentation <https://developers.openai.com/api/docs/guides/tools-connectors-mcp>`_

Remote MCP Tools
----------------

Use the ``McpTool`` class to connect a remote MCP server::

    use Symfony\AI\Platform\Bridge\OpenAi\Gpt\McpTool;
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = PlatformFactory::create($apiKey);

    $mcpTool = new McpTool(
        serverLabel: 'dmcp',
        serverUrl: 'https://dmcp-server.deno.dev/sse',
        requireApproval: 'never',
        allowedTools: ['roll'],
    );

    $result = $platform->invoke('gpt-4o-mini', new MessageBag(
        Message::ofUser('Roll a six-sided die for me.'),
    ), [
        'tools' => [$mcpTool],
    ]);

Built-in Connectors
-------------------

Use the ``McpConnector`` class to enable a built-in OpenAI connector::

    use Symfony\AI\Platform\Bridge\OpenAi\Gpt\McpConnector;

    $connector = new McpConnector(
        connectorId: 'connector_gmail',
        serverLabel: 'gmail',
        authorization: $oauthAccessToken,
    );

    $result = $platform->invoke('gpt-4o-mini', new MessageBag(
        Message::ofUser('Summarize my latest unread emails.'),
    ), [
        'tools' => [$connector],
    ]);

Approval Configuration
----------------------

Both ``McpTool`` and ``McpConnector`` accept a ``requireApproval`` parameter to control whether the model
needs approval before executing tools.

**Simple modes** — pass a string:

- ``'never'`` — skip approval for all tools (default)
- ``'always'`` — require approval before every tool call

**Granular control** — use ``ApprovalFilter`` to skip approval only for specific tools::

    use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ApprovalFilter;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt\McpTool;

    $mcpTool = new McpTool(
        serverLabel: 'my-server',
        serverUrl: 'https://example.com/mcp',
        requireApproval: new ApprovalFilter(never: ['safe_read_tool']),
    );

This will skip approval for ``safe_read_tool`` while requiring it for all other tools on the server.

Filtering Tools
---------------

Use ``allowedTools`` to restrict which tools the model can access from an MCP server or connector.
This reduces token costs and latency when only a subset of available tools is needed::

    $mcpTool = new McpTool(
        serverLabel: 'dmcp',
        serverUrl: 'https://dmcp-server.deno.dev/sse',
        requireApproval: 'never',
        allowedTools: ['roll'],
    );

Example
-------

See `examples/openai/remote-mcp.php`_ for a complete working example.

.. _`examples/openai/remote-mcp.php`: https://github.com/symfony/ai/blob/main/examples/openai/remote-mcp.php

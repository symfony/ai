OpenAI Server Tools
===================

Server tools are built-in capabilities hosted by OpenAI that let the model perform actions without a custom tool implementation. They run on OpenAI's servers and are available through the Responses API used by the OpenAI (GPT), Azure OpenAI, and Scaleway bridges.

Overview
--------

Built-in tools are enabled by passing the ``tools`` option to ``Platform::invoke()``. Each entry is an associative array with a ``type`` key, exactly as expected by the Responses API:

- **Web Search** - Searches the web and grounds the answer with citations
- **File Search** - Searches your uploaded files / vector stores
- **Code Interpreter** - Runs code in a sandboxed environment
- **Image Generation** - Generates images inline
- **Hosted MCP** - Calls tools exposed by a remote MCP server
- **Computer Use** / **Local Shell** - Requests client-side actions

When a built-in tool runs, the model reports it as a dedicated output item next to the
assistant message. Each item is converted into a typed result (see `Result Types`_), so a
response that searched the web before answering returns a
``Result\MultiPartResult`` containing a ``Result\WebSearchResult`` and the
``Result\TextResult`` with the answer. ``$result->asText()`` returns only the
answer text. Passing that result to ``Message::ofAssistant()`` preserves the
web search call when the assistant turn is sent back to the provider.

Available Server Tools
----------------------

Web Search
~~~~~~~~~~

::

    $messages = new MessageBag(
        Message::ofUser('What are the latest developments in quantum computing?')
    );

    $result = $platform->invoke('gpt-4o-mini', $messages, [
        'tools' => [
            ['type' => 'web_search'],
        ],
    ]);

File Search
~~~~~~~~~~~

::

    $result = $platform->invoke('gpt-4o-mini', $messages, [
        'tools' => [
            ['type' => 'file_search', 'vector_store_ids' => ['vs_1234']],
        ],
    ]);

Code Interpreter
~~~~~~~~~~~~~~~~

::

    $result = $platform->invoke('gpt-4o-mini', $messages, [
        'tools' => [
            ['type' => 'code_interpreter', 'container' => ['type' => 'auto']],
        ],
    ]);

Image Generation
~~~~~~~~~~~~~~~~

::

    $result = $platform->invoke('gpt-4o-mini', $messages, [
        'tools' => [
            ['type' => 'image_generation'],
        ],
    ]);

Hosted MCP
~~~~~~~~~~

::

    $result = $platform->invoke('gpt-4o-mini', $messages, [
        'tools' => [
            [
                'type' => 'mcp',
                'server_label' => 'deepwiki',
                'server_url' => 'https://mcp.deepwiki.com/mcp',
                'require_approval' => 'never',
            ],
        ],
    ]);

Computer Use and Local Shell
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Computer use and local shell ask the *client* to perform an action and send the result
back. The corresponding output items are surfaced as ``Result\ComputerCallResult`` and
``Result\LocalShellCallResult`` so you can inspect the requested action; executing it and
replaying the output is left to your application.

Using Multiple Server Tools
---------------------------

You can enable multiple built-in tools simultaneously::

    $result = $platform->invoke('gpt-4o-mini', $messages, [
        'tools' => [
            ['type' => 'web_search'],
            ['type' => 'code_interpreter', 'container' => ['type' => 'auto']],
        ],
    ]);

Result Types
------------

Each built-in tool output item maps to a typed result:

- ``web_search_call`` - ``Result\WebSearchResult``
- ``file_search_call`` - ``Result\FileSearchResult``
- ``code_interpreter_call`` - ``Result\ExecutableCodeResult`` (plus ``Result\CodeExecutionResult`` for the output)
- ``image_generation_call`` - ``Result\BinaryResult``
- ``mcp_call`` - ``Result\McpCallResult``
- ``mcp_list_tools`` - ``Result\McpListToolsResult``
- ``mcp_approval_request`` - ``Result\McpApprovalRequestResult``
- ``computer_call`` - ``Result\ComputerCallResult``
- ``local_shell_call`` - ``Result\LocalShellCallResult``

Examples
--------

Complete working examples:

- `examples/openai/web-search.php`_
- `examples/openai/file-search.php`_ (requires an existing vector store)
- `examples/openai/code-interpreter.php`_
- `examples/openai/image-generation.php`_
- `examples/openai/mcp.php`_ (hosted MCP server)

Limitations
-----------

- Not every model supports every built-in tool; check the OpenAI documentation
- Built-in tools may have usage quotas and additional billing
- Streaming emits completed web searches as ``WebSearchComplete`` deltas;
  other built-in tool results are available on non-streamed responses

.. _`examples/openai/web-search.php`: https://github.com/symfony/ai/blob/main/examples/openai/web-search.php
.. _`examples/openai/file-search.php`: https://github.com/symfony/ai/blob/main/examples/openai/file-search.php
.. _`examples/openai/code-interpreter.php`: https://github.com/symfony/ai/blob/main/examples/openai/code-interpreter.php
.. _`examples/openai/image-generation.php`: https://github.com/symfony/ai/blob/main/examples/openai/image-generation.php
.. _`examples/openai/mcp.php`: https://github.com/symfony/ai/blob/main/examples/openai/mcp.php

Anthropic Server Tools
======================

Anthropic provides built-in server-side tools that allow the model to perform specific actions like searching the
web or executing code in a sandboxed environment.

Overview
--------

Anthropic's server tools can be enabled when calling the model. These tools are executed on Anthropic's
infrastructure. Enable them with the ``server_tools`` option, a map of tool name to parameters (``true`` enables a
tool with no extra parameters)::

    $result = $platform->invoke('claude-sonnet-4-5-20250929', $messages, [
        'server_tools' => [
            'web_search' => true,
            'code_execution' => true,
        ],
    ]);

The bridge maps each recognized name to Anthropic's versioned tool ``type``. Only ``web_search`` and
``code_execution`` are mapped, because those are the only server tools whose result blocks
:class:`Symfony\\AI\\Platform\\Bridge\\Anthropic\\ResultConverter` currently understands. An unrecognized name
throws :class:`Symfony\\AI\\Platform\\Exception\\InvalidArgumentException`.

Available Server Tools
----------------------

Web Search
~~~~~~~~~~

The Web Search tool (``web_search``) lets the model search the web and ground its response in the results::

    $result = $platform->invoke('claude-sonnet-4-5-20250929', $messages, [
        'server_tools' => [
            'web_search' => ['max_uses' => 3],
        ],
    ]);

Any parameters given (e.g. ``max_uses``, ``allowed_domains``, ``blocked_domains``, ``user_location``) are merged
into the tool definition Anthropic sends, and this also lets a caller override the versioned ``type`` the bridge
defaults to::

    $result = $platform->invoke('claude-sonnet-5', $messages, [
        'server_tools' => [
            'web_search' => ['type' => 'web_search_20260209', 'max_uses' => 3],
        ],
    ]);

The bridge defaults to ``web_search_20250305``, the basic variant every Claude model supports. Anthropic also
offers ``web_search_20260209`` (dynamic filtering, Claude 4.6 and later) and ``web_search_20260318`` (adds
``response_inclusion``). A caller can opt into either per call, without falling back to the raw ``tools``
escape hatch.

.. caution::

    From ``web_search_20260209`` on, Anthropic runs the search from inside code execution to filter the
    results, and provisions that itself, so ``code_execution`` is not declared alongside it. Two consequences:
    the response then also carries code execution blocks that
    :class:`Symfony\\AI\\Platform\\Bridge\\Anthropic\\ResultConverter` does not convert, and ``allowed_callers``
    defaults to code execution, so a model without programmatic tool calling needs
    ``'allowed_callers' => ['direct']`` or the API answers 400.

Code Execution
~~~~~~~~~~~~~~

Anthropic provides two main tools for code execution:

- **Bash** (``bash_code_execution``) - Allows the model to run bash commands.
- **Text Editor** (``text_editor_code_execution``) - Allows the model to create and edit files, often used in conjunction with Python execution.

Both are enabled together through the single ``code_execution`` server tool::

    $result = $platform->invoke('claude-sonnet-4-5-20250929', $messages, [
        'server_tools' => [
            'code_execution' => true,
        ],
    ]);

Other Server Tools
~~~~~~~~~~~~~~~~~~

Anthropic offers further server tools (e.g. ``web_fetch``) that the bridge does not map to ``server_tools`` yet,
because their result blocks are not converted into typed results. These remain reachable by passing the raw
Anthropic tool spec through the ``tools`` option, which is forwarded to the request body as-is::

    $result = $platform->invoke('claude-sonnet-4-5-20250929', $messages, [
        'tools' => [[
            'type' => 'web_fetch_20250910',
            'name' => 'web_fetch',
        ]],
    ]);

Handling Results
----------------

When server tools are used, the model may return multiple content blocks. Symfony AI abstracts these into a
:class:`Symfony\\AI\\Platform\\Result\\MultiPartResult`.

The individual parts can be any `ResultInterface` instances, but in practice they consist of the following result types:

- :class:`Symfony\\AI\\Platform\\Result\\TextResult` - Normal text response.
- :class:`Symfony\\AI\\Platform\\Result\\ExecutableCodeResult` - The code the model intended to run.
- :class:`Symfony\\AI\\Platform\\Result\\CodeExecutionResult` - The output of the executed code (stdout/stderr).
- :class:`Symfony\\AI\\Platform\\Result\\WebSearchResult` - One search the model performed. The converter merges
  the ``server_tool_use`` call and its matching ``web_search_tool_result`` into a single part carrying the query,
  id, and status together.

Passing the result to ``Message::ofAssistant()`` replays the search on the next turn, for buffered and streamed
results alike: Anthropic rejects either of the two blocks without the other, so both are kept and re-sent as a
pair. A search whose blocks came from another provider is dropped rather than re-sent, so an assistant turn
moving between bridges keeps its text.

.. note::

    Streaming reports a completed web search as a
    :class:`Symfony\\AI\\Platform\\Result\\Stream\\Delta\\WebSearchComplete` delta, one per search, once its
    result block arrives. The remaining server tool blocks are still dropped from streams; they are converted
    for non-streaming requests only.

.. caution::

    Web searches initiated from Anthropic code execution cannot be replayed, because the surrounding code
    execution blocks are not converted. Replaying such a turn throws
    :class:`Symfony\\AI\\Platform\\Exception\\InvalidArgumentException` rather than sending Anthropic a search
    it will reject.

.. caution::

    :class:`Symfony\\AI\\Platform\\Result\\WebSearchResult` has no field for individual search hits (URL, title,
    page age). Anthropic reports those on the ``web_search_tool_result`` block, but carrying them would require
    changing a Platform-wide class the OpenResponses bridge also populates, so today the query, id and status
    round-trip on the result itself while the hits survive only inside the replay payload. Surfacing search hits
    and handling ``citations`` on text blocks are open follow-ups.

Example
-------

See `examples/anthropic/server-tools-code-execution.php`_ and `examples/anthropic/server-tools-web-search.php`_ for
complete working examples, and `examples/anthropic/server-tools-web-search-roundtrip.php`_ for a two-turn
conversation continuing after a search.

.. _`examples/anthropic/server-tools-code-execution.php`: https://github.com/symfony/ai/blob/main/examples/anthropic/server-tools-code-execution.php
.. _`examples/anthropic/server-tools-web-search.php`: https://github.com/symfony/ai/blob/main/examples/anthropic/server-tools-web-search.php
.. _`examples/anthropic/server-tools-web-search-roundtrip.php`: https://github.com/symfony/ai/blob/main/examples/anthropic/server-tools-web-search-roundtrip.php

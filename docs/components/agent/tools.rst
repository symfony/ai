Built-in Tools
==============

The Agent component ships with a collection of ready-made tool bridges that can be added to any agent.
Each bridge is a separate Composer package and provides one or more tools registered via the :class:`Symfony\\AI\\Agent\\Toolbox\\Attribute\\AsTool` attribute -
except for the MCP bridge, which discovers its tools from a remote server at runtime.

.. tip::

    All built-in tools can be used together with the :class:`Symfony\\AI\\Agent\\Toolbox\\FaultTolerantToolbox` decorator
    to handle errors gracefully instead of letting them propagate to the agent loop.

Web Search & Content
--------------------

Brave Search
~~~~~~~~~~~~

Searches the web using the `Brave Search API`_.

.. code-block:: terminal

    $ composer require symfony/ai-brave-tool

`Brave Search Example`_

SerpApi
~~~~~~~

Searches the web via Google through the `SerpApi`_ service.

.. code-block:: terminal

    $ composer require symfony/ai-serp-api-tool

`SerpApi Example`_

Tavily
~~~~~~

Searches the web and extracts content using the `Tavily`_ AI search service.

.. code-block:: terminal

    $ composer require symfony/ai-tavily-tool

`Tavily Example`_

Scraper
~~~~~~~

Loads the visible text and title of a webpage. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-scraper-tool

Firecrawl
~~~~~~~~~

Scrapes, crawls, and maps websites using the `Firecrawl`_ service.

.. code-block:: terminal

    $ composer require symfony/ai-firecrawl-tool

`Firecrawl Scrape Example`_ | `Firecrawl Crawl Example`_ | `Firecrawl Map Example`_

Wikipedia
~~~~~~~~~

Searches Wikipedia and retrieves article content. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-wikipedia-tool

`Wikipedia Example`_

Ollama Web Tools
~~~~~~~~~~~~~~~~

Performs web searches and fetches webpage content via `Ollama`_'s built-in search capabilities.

.. code-block:: terminal

    $ composer require symfony/ai-ollama-tool

`Ollama Web Search Example`_ | `Ollama Webpage Fetch Example`_

Location & Maps
---------------

Mapbox
~~~~~~

Converts addresses to coordinates (geocoding) and coordinates to addresses (reverse geocoding)
using the `Mapbox Geocoding API`_.

.. code-block:: terminal

    $ composer require symfony/ai-mapbox-tool

`Mapbox Geocode Example`_ | `Mapbox Reverse Geocode Example`_

Weather
-------

OpenMeteo
~~~~~~~~~

Returns current weather and forecasts using the free `Open-Meteo API`_. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-open-meteo-tool

`Weather Example`_

Video
-----

YouTube Transcriber
~~~~~~~~~~~~~~~~~~~

Retrieves the transcript of a YouTube video. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-youtube-tool

`YouTube Transcriber Example`_

Filesystem
----------

Filesystem
~~~~~~~~~~

Provides sandboxed file operations for the agent. Supports reading, writing, listing, and managing files
within a defined base path.

.. code-block:: terminal

    $ composer require symfony/ai-filesystem-tool

.. caution::

    Always restrict the ``basePath`` to a directory the agent is allowed to access.
    The ``allowDelete`` option is disabled by default for safety.

Date & Time
-----------

Clock
~~~~~

Returns the current date and time. Compatible with `Symfony Clock`_. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-clock-tool

`Clock Example`_

Model Context Protocol
----------------------

MCP
~~~

Exposes the tools advertised by a remote `Model Context Protocol`_ server, discovered from its
``tools/list`` and called through ``tools/call``.

.. code-block:: terminal

    $ composer require symfony/ai-mcp-tool

Unlike the other bridges, this one has no fixed set of tools - the server decides what it offers.
An agent is not an MCP client, though: it never asks a server for a prompt or reads one of its
resources, it only draws tools from it, which is why the bridge models a *toolset* behind an MCP
connection rather than the server itself. It asks the server what tools it has, hands the answer to
the toolbox as ordinary tool definitions, and prefixes every name with the server's short name, so
several servers can be attached to one agent without their tool names colliding::

    use Mcp\Client;
    use Mcp\Client\Transport\StdioTransport;
    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Bridge\Mcp\ClientToolset;
    use Symfony\AI\Agent\Bridge\Mcp\McpToolAdapter;
    use Symfony\AI\Agent\Bridge\Mcp\McpToolFactory;
    use Symfony\AI\Agent\Toolbox\Toolbox;

    $toolset = new ClientToolset(
        'filesystem',
        Client::builder()->build(),
        new StdioTransport('npx', ['-y', '@modelcontextprotocol/server-filesystem', '/tmp']),
    );

    $toolbox = new Toolbox([new McpToolAdapter($toolset)], new McpToolFactory());
    $agent = new Agent($platform, 'gpt-4o-mini', toolbox: $toolbox);

A ``read_file`` tool of that server reaches the model as ``filesystem_read_file``; pass a second
argument to :class:`Symfony\\AI\\Agent\\Bridge\\Mcp\\McpToolAdapter` to choose the prefix yourself.

To combine remote tools with local ``#[AsTool]`` services in one toolbox, chain the factories::

    use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
    use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;

    $toolbox = new Toolbox(
        [new McpToolAdapter($toolset), new Clock()],
        new ChainFactory([new McpToolFactory(), new ReflectionToolFactory()]),
    );

`MCP Example`_

.. note::

    In a Symfony application, configure the connection once with the MCP bundle's ``mcp.clients``
    option and point an agent at it with the AI bundle's ``tools.mcp_servers`` option - see
    :doc:`/bundles/ai-bundle`. The bundles then share one connection instead of opening a second
    one to the same server.

Retrieval Augmented Generation
------------------------------

SimilaritySearch
~~~~~~~~~~~~~~~~

Performs a similarity search against the :doc:`/components/store` to enable RAG (Retrieval Augmented Generation).
Requires a vectorizer and a configured vector store.

.. code-block:: terminal

    $ composer require symfony/ai-similarity-search-tool

See :doc:`/components/agent` for a full RAG integration example.

.. _`Brave Search API`: https://brave.com/search/api/
.. _`Brave Search Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/brave.php
.. _`SerpApi`: https://serpapi.com/
.. _`SerpApi Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/serpapi.php
.. _`Tavily`: https://tavily.com/
.. _`Tavily Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/tavily.php
.. _`Firecrawl`: https://www.firecrawl.dev/
.. _`Firecrawl Scrape Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/firecrawl-scrape.php
.. _`Firecrawl Crawl Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/firecrawl-crawl.php
.. _`Firecrawl Map Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/firecrawl-map.php
.. _`Mapbox Geocoding API`: https://docs.mapbox.com/api/search/geocoding/
.. _`Mapbox Geocode Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/mapbox-geocode.php
.. _`Mapbox Reverse Geocode Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/mapbox-reverse-geocode.php
.. _`Open-Meteo API`: https://open-meteo.com/
.. _`Weather Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/weather-event.php
.. _`Wikipedia Example`: https://github.com/symfony/ai/blob/main/examples/openai/toolcall-stream.php
.. _`YouTube Transcriber Example`: https://github.com/symfony/ai/blob/main/examples/openai/toolcall.php
.. _`Ollama`: https://ollama.com/
.. _`Ollama Web Search Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/ollama-web-search.php
.. _`Ollama Webpage Fetch Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/ollama-webpage-fetch.php
.. _`Clock Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/clock.php
.. _`Model Context Protocol`: https://modelcontextprotocol.io
.. _`MCP Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/mcp.php
.. _`Symfony Clock`: https://symfony.com/doc/current/components/clock.html

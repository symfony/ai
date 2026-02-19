Built-in Tools
==============

Symfony AI ships with a collection of ready-made tool bridges that can be added to any agent.
Each bridge is a separate Composer package and provides one or more tools registered via the :class:`Symfony\\AI\\Agent\\Toolbox\\Attribute\\AsTool` attribute.

.. tip::

    All built-in tools can be used together with the :class:`Symfony\\AI\\Agent\\Toolbox\\FaultTolerantToolbox` decorator
    to handle errors gracefully instead of letting them propagate to the agent loop.

Web Search & Content
---------------------

Brave Search
~~~~~~~~~~~~

Searches the web using the `Brave Search API`_.

.. code-block:: terminal

    $ composer require symfony/ai-brave-tool

**Tool name:** ``brave_search``

**Required:** `Brave Search API key`_

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Brave\Brave;
    use Symfony\Component\HttpClient\HttpClient;

    $brave = new Brave(HttpClient::create(), $apiKey);

**Parameters:**

* ``$query`` (string) – Search query (max 500 characters)
* ``$count`` (int, default: 20) – Number of results to return
* ``$offset`` (int, default: 0, max: 9) – Number of results to skip

**Returns:** array of ``{title, description, url}``

`Brave Search Example`_

SerpApi
~~~~~~~

Searches the web via Google through the `SerpApi`_ service.

.. code-block:: terminal

    $ composer require symfony/ai-serp-api-tool

**Tool name:** ``serpapi``

**Required:** `SerpApi API key`_

.. code-block:: php

    use Symfony\AI\Agent\Bridge\SerpApi\SerpApi;
    use Symfony\Component\HttpClient\HttpClient;

    $serpApi = new SerpApi(HttpClient::create(), $apiKey);

**Parameters:**

* ``$query`` (string) – Search query

**Returns:** array of ``{title, link, content}``

`SerpApi Example`_

Tavily
~~~~~~

Searches the web and extracts content using the `Tavily`_ AI search service.

.. code-block:: terminal

    $ composer require symfony/ai-tavily-tool

**Tool names:** ``tavily_search``, ``tavily_extract``

**Required:** `Tavily API key`_

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Tavily\Tavily;
    use Symfony\Component\HttpClient\HttpClient;

    $tavily = new Tavily(HttpClient::create(), $apiKey);

* ``tavily_search($query)`` – Search the web
* ``tavily_extract(array $urls)`` – Extract content from given URLs

`Tavily Example`_

Scraper
~~~~~~~

Loads the visible text and title of a webpage. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-scraper-tool

**Tool name:** ``scraper``

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Scraper\Scraper;
    use Symfony\Component\HttpClient\HttpClient;

    $scraper = new Scraper(HttpClient::create());

**Parameters:**

* ``$url`` (string) – URL to scrape

**Returns:** ``{title, content}``

Firecrawl
~~~~~~~~~

Scrapes, crawls, and maps websites using the `Firecrawl`_ service.

.. code-block:: terminal

    $ composer require symfony/ai-firecrawl-tool

**Tool names:** ``firecrawl_scrape``, ``firecrawl_crawl``, ``firecrawl_map``

**Required:** `Firecrawl API key`_

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Firecrawl\Firecrawl;
    use Symfony\Component\HttpClient\HttpClient;

    $firecrawl = new Firecrawl(HttpClient::create(), $apiKey, 'https://api.firecrawl.dev');

* ``firecrawl_scrape($url)`` – Returns ``{url, markdown, html}``
* ``firecrawl_crawl($url)`` – Crawls all pages under a URL
* ``firecrawl_map($url)`` – Returns all URLs found on a site

`Firecrawl Scrape Example`_ | `Firecrawl Crawl Example`_ | `Firecrawl Map Example`_

Wikipedia
~~~~~~~~~

Searches Wikipedia and retrieves article content. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-wikipedia-tool

**Tool names:** ``wikipedia_search``, ``wikipedia_article``

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia;
    use Symfony\Component\HttpClient\HttpClient;

    $wikipedia = new Wikipedia(HttpClient::create()); // defaults to English
    $wikipedia = new Wikipedia(HttpClient::create(), 'de'); // German Wikipedia

* ``wikipedia_search($query)`` – Returns matching article titles
* ``wikipedia_article($title)`` – Returns the full article content

`Wikipedia Example`_

Ollama Web Tools
~~~~~~~~~~~~~~~~

Performs web searches and fetches webpage content via `Ollama`_'s built-in search capabilities.

.. code-block:: terminal

    $ composer require symfony/ai-ollama-tool

**Tool names:** ``web_search``, ``fetch_webpage``

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Ollama\Ollama;
    use Symfony\Component\HttpClient\HttpClient;

    $ollama = new Ollama(HttpClient::create());

* ``web_search($query, $maxResults = 5)`` – Searches the web
* ``fetch_webpage($url)`` – Returns ``{title, content, links}``

`Ollama Web Search Example`_ | `Ollama Webpage Fetch Example`_

Location & Maps
---------------

Mapbox
~~~~~~

Converts addresses to coordinates (geocoding) and coordinates to addresses (reverse geocoding)
using the `Mapbox Geocoding API`_.

.. code-block:: terminal

    $ composer require symfony/ai-mapbox-tool

**Tool names:** ``geocode``, ``reverse_geocode``

**Required:** `Mapbox access token`_

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Mapbox\Mapbox;
    use Symfony\Component\HttpClient\HttpClient;

    $mapbox = new Mapbox(HttpClient::create(), $accessToken);

* ``geocode($address, $limit = 1)`` – Returns coordinates for an address
* ``reverse_geocode($longitude, $latitude, $limit = 1)`` – Returns address for coordinates

`Mapbox Geocode Example`_ | `Mapbox Reverse Geocode Example`_

Weather
-------

OpenMeteo
~~~~~~~~~

Returns current weather and forecasts using the free `Open-Meteo API`_. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-open-meteo-tool

**Tool names:** ``weather_current``, ``weather_forecast``

.. code-block:: php

    use Symfony\AI\Agent\Bridge\OpenMeteo\OpenMeteo;
    use Symfony\Component\HttpClient\HttpClient;

    $openMeteo = new OpenMeteo(HttpClient::create());

* ``weather_current($latitude, $longitude)`` – Returns current conditions
* ``weather_forecast($latitude, $longitude, $days = 7)`` – Returns forecast (max 16 days)

`Weather Example`_

Video
-----

YouTube Transcriber
~~~~~~~~~~~~~~~~~~~

Retrieves the transcript of a YouTube video. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-youtube-tool

**Tool name:** ``youtube_transcript``

**Additional dependency:** ``mrmysql/youtube-transcript``

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Youtube\YoutubeTranscriber;
    use Symfony\Component\HttpClient\HttpClient;

    $youtube = new YoutubeTranscriber(HttpClient::create());

**Parameters:**

* ``$videoId`` (string) – YouTube video ID (e.g. ``dQw4w9WgXcQ``)

**Returns:** transcript as plain text string

`YouTube Transcriber Example`_

Filesystem
----------

Filesystem
~~~~~~~~~~

Provides sandboxed file operations for the agent. Supports reading, writing, listing, and managing files
within a defined base path.

.. code-block:: terminal

    $ composer require symfony/ai-filesystem-tool

**Tool names:** ``filesystem_read``, ``filesystem_write``, ``filesystem_append``, ``filesystem_copy``,
``filesystem_move``, ``filesystem_delete``, ``filesystem_mkdir``, ``filesystem_exists``,
``filesystem_info``, ``filesystem_list``

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Filesystem\Filesystem;
    use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

    $filesystem = new Filesystem(
        new SymfonyFilesystem(),
        basePath: '/var/data/agent',
        allowWrite: true,
        allowDelete: false,
        allowedExtensions: [],
        deniedExtensions: ['php', 'phar', 'sh', 'exe', 'bat'],
        deniedPatterns: ['.*', '*.env*'],
        maxReadSize: 10 * 1024 * 1024, // 10 MB
    );

.. caution::

    Always restrict the ``basePath`` to a directory the agent is allowed to access.
    The ``allowDelete`` option is disabled by default for safety.

**Available operations:**

* ``filesystem_read($path)`` – Returns file content
* ``filesystem_write($path, $content)`` – Creates or overwrites a file
* ``filesystem_append($path, $content)`` – Appends content to a file
* ``filesystem_copy($source, $destination)`` – Copies a file
* ``filesystem_move($source, $destination)`` – Moves or renames a file
* ``filesystem_delete($path)`` – Deletes a file or directory
* ``filesystem_mkdir($path)`` – Creates a directory
* ``filesystem_exists($path)`` – Checks if a path exists
* ``filesystem_info($path)`` – Returns file metadata (size, modified time, permissions)
* ``filesystem_list($path, $recursive = false)`` – Lists directory contents

Date & Time
-----------

Clock
~~~~~

Returns the current date and time. Compatible with `Symfony Clock`_. No API key required.

.. code-block:: terminal

    $ composer require symfony/ai-clock-tool

**Tool name:** ``clock``

.. code-block:: php

    use Symfony\AI\Agent\Bridge\Clock\Clock;
    use Symfony\Component\Clock\Clock as SymfonyClock;

    $clock = new Clock(new SymfonyClock(), 'Europe/Berlin');

**Constructor parameters:**

* ``$clock`` (ClockInterface) – Clock implementation (optional, defaults to system clock)
* ``$timezone`` (string|null) – Timezone to use (optional)

`Clock Example`_

Retrieval Augmented Generation
-------------------------------

SimilaritySearch
~~~~~~~~~~~~~~~~

Performs a similarity search against a `Store Component`_ to enable RAG (Retrieval Augmented Generation).
Requires a vectorizer and a configured vector store.

.. code-block:: terminal

    $ composer require symfony/ai-similarity-search-tool

**Tool name:** ``similarity_search``

.. code-block:: php

    use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;

    $similaritySearch = new SimilaritySearch($vectorizer, $store);

**Parameters:**

* ``$searchTerm`` (string) – Query to find similar documents

See :doc:`/components/agent` for a full RAG integration example.

.. _`Brave Search API`: https://brave.com/search/api/
.. _`Brave Search API key`: https://api.search.brave.com/
.. _`Brave Search Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/brave.php
.. _`SerpApi`: https://serpapi.com/
.. _`SerpApi API key`: https://serpapi.com/dashboard
.. _`SerpApi Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/serpapi.php
.. _`Tavily`: https://tavily.com/
.. _`Tavily API key`: https://app.tavily.com/
.. _`Tavily Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/tavily.php
.. _`Firecrawl`: https://www.firecrawl.dev/
.. _`Firecrawl API key`: https://www.firecrawl.dev/app/api-keys
.. _`Firecrawl Scrape Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/firecrawl-scrape.php
.. _`Firecrawl Crawl Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/firecrawl-crawl.php
.. _`Firecrawl Map Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/firecrawl-map.php
.. _`Mapbox Geocoding API`: https://docs.mapbox.com/api/search/geocoding/
.. _`Mapbox access token`: https://account.mapbox.com/
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
.. _`Symfony Clock`: https://symfony.com/doc/current/components/clock.html
.. _`Store Component`: /components/store

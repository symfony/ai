Symfony AI - Tools
===================

The Tools component provides a collection of third-party tools for AI agents, including web search and knowledge retrieval capabilities.

Installation
------------

Install the component using Composer:

.. code-block:: terminal

    $ composer require symfony/ai-tools

Available Tools
---------------

The Toolbox component includes ready-to-use tools that can be integrated with AI agents:

Brave Search Tool
~~~~~~~~~~~~~~~~~

Provides web search functionality using the Brave Search API::

    use Symfony\AI\Tools\Tool\Brave;
    use Symfony\Component\HttpClient\HttpClient;

    $httpClient = HttpClient::create();
    $brave = new Brave($httpClient, 'your-brave-api-key');

    // Search the web
    $results = $brave('latest news about Symfony');

The Brave tool returns an array of search results with title, description, and URL for each result.

**Configuration Options**

You can pass additional options to customize the search behavior::

    $brave = new Brave($httpClient, $apiKey, [
        'country' => 'US',
        'safesearch' => 'moderate',
        'freshness' => 'pw', // Past week
    ]);

See the `Brave Search API documentation`_ for all available options.

Wikipedia Tool
~~~~~~~~~~~~~~

Provides search and article retrieval from Wikipedia::

    use Symfony\AI\Tools\Tool\Wikipedia;
    use Symfony\Component\HttpClient\HttpClient;

    $httpClient = HttpClient::create();
    $wikipedia = new Wikipedia($httpClient);

    // Search for articles
    $searchResults = $wikipedia->search('artificial intelligence');

    // Get article content
    $article = $wikipedia->article('Artificial intelligence');

The Wikipedia tool supports multiple languages by passing a locale parameter::

    $wikipedia = new Wikipedia($httpClient, 'de'); // German Wikipedia

**Tool Methods**

The Wikipedia tool provides two methods that can be used as separate tools:

* ``search``: Search for articles by query
* ``article``: Retrieve full article content by title

Agent Integration
-----------------

These tools are designed to work with the `Agent Component`_ and can be registered using the ``#[AsTool]`` attribute::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Tools\Tool\Brave;
    use Symfony\AI\Tools\Tool\Wikipedia;

    // Initialize HTTP client and tools
    $httpClient = HttpClient::create();
    $brave = new Brave($httpClient, $braveApiKey);
    $wikipedia = new Wikipedia($httpClient);

    // Create toolbox and processor
    $toolbox = new Toolbox([$brave, $wikipedia]);
    $processor = new AgentProcessor($toolbox);

    // Create agent with tools
    $agent = new Agent($platform, $model, [$processor], [$processor]);

Tool Configuration
------------------

Each tool is automatically configured with the ``#[AsTool]`` attribute:

* **Brave Search**: Registered as ``brave_search`` tool
* **Wikipedia Search**: Registered as ``wikipedia_search`` tool  
* **Wikipedia Article**: Registered as ``wikipedia_article`` tool

Requirements
------------

* PHP 8.2 or higher
* Symfony HTTP Client component
* Brave Search API key (for Brave tool only)

The Wikipedia tool requires no API key as it uses the public Wikipedia API.

**Code Examples**

* `Brave Search Example`_
* `Wikipedia Search Example`_

.. _`Brave Search API documentation`: https://api-dashboard.search.brave.com/app/documentation/web-search/query
.. _`Agent Component`: https://github.com/symfony/ai-agent
.. _`Brave Search Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/brave.php
.. _`Wikipedia Search Example`: https://github.com/symfony/ai/blob/main/examples/toolbox/wikipedia.php
Perplexity
==========

The Perplexity bridge talks to the `Perplexity Agent API`_, which answers with real-time web search and
follows the OpenAI Responses API schema. Next to Perplexity's own models, the Agent API routes to models of
other providers (OpenAI, Anthropic, Google, xAI, ...) with the same built-in tools.

Setup
-----

Install the bridge and get an API key (starting with ``pplx-``) from the `Perplexity API console`_:

.. code-block:: terminal

    $ composer require symfony/ai-perplexity-platform

Presets and Models
------------------

The model catalog exposes two kinds of names:

* the presets ``fast``, ``low``, ``medium``, ``high`` and ``xhigh``, sent as ``preset``. A preset bundles a
  model, a system prompt, the web search configuration and the number of research steps, from quick lookups
  (``fast``) to open-ended agentic work with a code sandbox (``xhigh``). See the `presets`_ documentation;
* the provider-prefixed model ``perplexity/sonar``, sent as ``model``. Any other Agent API model, like
  ``openai/gpt-5.5`` or ``anthropic/claude-sonnet-4-6``, can be added to the catalog::

    use Symfony\AI\Platform\Bridge\Perplexity\Factory;
    use Symfony\AI\Platform\Bridge\Perplexity\ModelCatalog;
    use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;
    use Symfony\AI\Platform\Capability;

    $modelCatalog = new ModelCatalog([
        'anthropic/claude-sonnet-4-6' => [
            'class' => Perplexity::class,
            'capabilities' => [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::TOOL_CALLING],
        ],
    ]);

    $platform = Factory::createPlatform($_ENV['PERPLEXITY_API_KEY'], modelCatalog: $modelCatalog);

Anthropic models require the ``max_output_tokens`` option.

.. note::

    The Sonar Chat Completions model names are deprecated and sent as the preset Perplexity recommends in
    their place: ``sonar`` and ``sonar-pro`` as ``fast``, ``sonar-reasoning`` and ``sonar-reasoning-pro`` as
    ``low``, ``sonar-deep-research`` as ``high``.

Usage
-----

Presets search the web on their own::

    use Symfony\AI\Platform\Bridge\Perplexity\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform($_ENV['PERPLEXITY_API_KEY']);

    $result = $platform->invoke('fast', new MessageBag(
        Message::ofUser('What are the latest developments in AI agents?'),
    ));

    echo $result->asText();

The ``stream`` option streams the answer, and the ``max_tokens`` option is sent as the Agent API
``max_output_tokens``. Every other option is passed to the Agent API as is, e.g. ``max_steps`` or
``reasoning``. The API rejects any field it does not know.

Web Search
~~~~~~~~~~

A model searches the web only when the ``web_search`` tool is part of the request. The tool also carries the
search filters (domains, recency, dates) and settings::

    $result = $platform->invoke('perplexity/sonar', $messages, [
        'tools' => [[
            'type' => 'web_search',
            'filters' => [
                'search_domain_filter' => ['wikipedia.org'],
                'search_recency_filter' => 'month',
            ],
        ]],
        'tool_choice' => ['type' => 'web_search'],
    ]);

Offering the tool lets the model decide whether to search, ``tool_choice`` forces it. See the
`web search tool`_ documentation for every filter and setting.

Search Results and Citations
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The answer references its sources with ``[1]``-style markers. The results of every web search of the run are
exposed as ``search_results`` metadata, and their URLs as ``citations`` metadata, where the first URL is the
source of ``[1]``::

    foreach ($result->getMetadata()->get('search_results', []) as $searchResult) {
        // $searchResult['id'], $searchResult['url'], $searchResult['title'], $searchResult['snippet'], ...
    }

Both are also set at the end of a streamed response.

Tool Calling
~~~~~~~~~~~~

Presets and models support function calling, so the bridge works with the
:class:`Symfony\\AI\\Agent\\Agent` and its toolbox. The Agent API rejects function names that collide with
its built-in tools, such as ``web_search``, ``fetch_url``, ``finance_search``, ``people_search`` or
``sandbox``.

System Prompt
~~~~~~~~~~~~~

A system message is sent as the Agent API ``instructions``, which replace the system prompt of a preset,
including its citation guidelines.

.. note::

    Perplexity's Agent API does not accept documents: PDF and other file inputs are not supported.

.. _Perplexity Agent API: https://docs.perplexity.ai/docs/agent-api/quickstart
.. _Perplexity API console: https://console.perplexity.ai
.. _presets: https://docs.perplexity.ai/docs/agent-api/presets
.. _web search tool: https://docs.perplexity.ai/docs/agent-api/tools/web-search

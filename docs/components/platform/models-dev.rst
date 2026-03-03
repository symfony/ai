Models.dev Platform
===================

The models.dev bridge provides auto-discovered model catalogs for many AI
providers using data from the `models.dev`_ community registry.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-models-dev-platform

Authentication
--------------

Each provider requires its own API key. Set it as an environment variable:

.. code-block:: bash

    # Example for DeepSeek
    DEEPSEEK_API_KEY=your-api-key

    # Example for Groq
    GROQ_API_KEY=your-api-key

Refer to each provider's documentation for how to obtain an API key.

Usage
-----

Using the PlatformFactory
~~~~~~~~~~~~~~~~~~~~~~~~~

The simplest way to get started is with ``PlatformFactory``, which auto-detects
the API base URL from the models.dev data::

    use Symfony\AI\Platform\Bridge\ModelsDev\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Auto-detect base URL from models.dev data
    $platform = PlatformFactory::create(
        provider: 'deepseek',
        apiKey: $_ENV['DEEPSEEK_API_KEY'],
    );

    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('What is the Symfony framework?'),
    );

    $result = $platform->invoke('deepseek-chat', $messages);
    echo $result->asText();

For providers where models.dev does not publish an API base URL, you must provide it
explicitly. The base URL should **not** include the ``/v1`` suffix as it will be added
automatically::

    $platform = PlatformFactory::create(
        provider: 'groq',
        apiKey: $_ENV['GROQ_API_KEY'],
        baseUrl: 'https://api.groq.com/openai',
    );

By default, the bridge uses the generic bridge for all OpenAI-compatible
providers. However, some providers require specialized API. In that case, the
bridge automatically detects and routes to specialized bridges when installed::

    // Anthropic bridge installed: routes automatically
    $platform = PlatformFactory::create('anthropic', $_ENV['ANTHROPIC_API_KEY']);

The factory is designed to be simple and opinionated, using models.dev definitions
for everything. Advanced options are available when needed::

    use Symfony\Component\HttpClient\HttpClient;

    $platform = PlatformFactory::create(
        provider: 'deepseek',
        apiKey: $_ENV['DEEPSEEK_API_KEY'],
        httpClient: HttpClient::create(), // Optional: custom HTTP client
        eventDispatcher: $dispatcher,     // Optional: for logging/monitoring
    );

.. note::

    The factory is intentionally minimal, if you need more flexibility, use the
    dedicated provider bridges (Anthropic, Gemini, etc.) or the Generic bridge
    directly.

Embeddings
~~~~~~~~~~

Embedding models are automatically detected and routed to the
``EmbeddingsModel`` class. Use them like any other embedding model::

    $platform = PlatformFactory::create(
        provider: 'openai',
        apiKey: $_ENV['OPENAI_API_KEY'],
        baseUrl: 'https://api.openai.com/v1',
    );

    $result = $platform->invoke('text-embedding-3-small', 'What is Symfony?');
    $vectors = $result->asVectors();

Streaming
~~~~~~~~~

All completions models include the ``OUTPUT_STREAMING`` capability. Enable
streaming as you would with any other platform::

    $result = $platform->invoke('deepseek-chat', $messages, [
        'stream' => true,
    ]);

    foreach ($result->getContent() as $chunk) {
        echo $chunk;
    }

Tool Calling
~~~~~~~~~~~~

Models that support tool calling are automatically flagged with the
``TOOL_CALLING`` capability::

    use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;

    $catalog = new ModelCatalog('deepseek');
    $model = $catalog->getModel('deepseek-chat');

    // Check if the model supports tool calling
    if ($model->supports(\Symfony\AI\Platform\Capability::TOOL_CALLING)) {
        // Use with an Agent that has tools configured
    }

Adding Custom Models
~~~~~~~~~~~~~~~~~~~~

If a model is missing from the data or you need to override its capabilities,
pass additional models when creating the ``ModelCatalog``::

    use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
    use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;
    use Symfony\AI\Platform\Capability;

    $catalog = new ModelCatalog('deepseek', additionalModels: [
        'deepseek-custom-finetune' => [
            'class' => CompletionsModel::class,
            'capabilities' => [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
            ],
        ],
    ]);

Additional models are merged with and take precedence over the bundled data.

Provider Registry
~~~~~~~~~~~~~~~~~

The ``ProviderRegistry`` gives you access to provider metadata::

    use Symfony\AI\Platform\Bridge\ModelsDev\ProviderRegistry;

    $registry = new ProviderRegistry();

    // List all available providers
    $providerIds = $registry->getProviderIds();
    // ['openai', 'anthropic', 'deepseek', 'groq', ...]

    // Check if a provider exists
    $registry->has('deepseek'); // true

    // Get provider name
    $registry->getProviderName('deepseek'); // "DeepSeek"

    // Get API base URL (null if not published by models.dev)
    $registry->getApiBaseUrl('deepseek'); // "https://api.deepseek.com"

Symfony Bundle Configuration
----------------------------

When using the AI Bundle, configure the models.dev bridge under the ``generic``
platform section. The ``ModelCatalog`` replaces the manually curated model
list:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            generic:
                deepseek:
                    base_url: 'https://api.deepseek.com'
                    api_key: '%env(DEEPSEEK_API_KEY)%'
                    model_catalog: 'Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog'
        agent:
            deepseek:
                platform: 'ai.platform.generic.deepseek'
                model: 'deepseek-chat'
                tools: false

    services:
        Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog:
            arguments:
                $providerId: 'deepseek'

Multiple Providers
~~~~~~~~~~~~~~~~~~

Configure multiple providers in the same application:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            generic:
                deepseek:
                    base_url: 'https://api.deepseek.com'
                    api_key: '%env(DEEPSEEK_API_KEY)%'
                    model_catalog: 'app.model_catalog.deepseek'
                groq:
                    base_url: 'https://api.groq.com/openai/v1'
                    api_key: '%env(GROQ_API_KEY)%'
                    model_catalog: 'app.model_catalog.groq'

    services:
        app.model_catalog.deepseek:
            class: 'Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog'
            arguments:
                $providerId: 'deepseek'

        app.model_catalog.groq:
            class: 'Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog'
            arguments:
                $providerId: 'groq'

Supported Providers
-------------------

The models.dev registry includes many providers. Use the ``ProviderRegistry``
to list all available providers and check which have auto-detected base URLs::

    $registry = new ProviderRegistry();
    foreach ($registry->getProviderIds() as $id) {
        $url = $registry->getApiBaseUrl($id) ?? '(manual)';
        echo sprintf("%s: %s\n", $id, $url);
    }

Resources
---------

 * `Contributing <https://symfony.com/doc/current/contributing/index.html>`_
 * `Report issues <https://github.com/symfony/ai/issues>`_ and
   `send Pull Requests <https://github.com/symfony/ai/pulls>`_
   in the `main Symfony AI repository <https://github.com/symfony/ai>`_

.. _models.dev: https://models.dev/

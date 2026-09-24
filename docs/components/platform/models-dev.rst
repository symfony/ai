Models.dev Platform
===================

The models.dev bridge brings model catalogs for many AI providers to Symfony AI,
sourced from the `models.dev`_ community registry and shipped as the standalone
``symfony/models-dev`` package.

Its main benefit is decoupling the **model catalog lifecycle** from the Symfony AI
release cycle. Every bridge ships a hand-curated catalog of known models that only
changes when you upgrade the bridge itself. The models.dev bridge replaces that with a
dynamic catalog you refresh independently: ``composer update symfony/models-dev`` picks
up newly released models and their capabilities without bumping ``symfony/ai-platform``
or editing any catalog by hand.

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

Using the Model Catalog
~~~~~~~~~~~~~~~~~~~~~~~

At its core the bridge provides a ``ModelCatalog`` that reads the models.dev data for a
given provider. It is wired with the model classes the matching bridge expects, so it drops
straight into that bridge in place of its bundled catalog.

For any OpenAI-compatible provider, pair it with the Generic bridge::

    use Symfony\AI\Platform\Bridge\Generic\Factory as GenericFactory;
    use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;

    $platform = GenericFactory::createPlatform(
        baseUrl: 'https://api.deepseek.com',
        apiKey: $_ENV['DEEPSEEK_API_KEY'],
        modelCatalog: new ModelCatalog('deepseek'),
    );

For a provider that needs a specialized bridge, pair it with that bridge. The catalog already
carries the model class that bridge requires (e.g. ``Claude`` for Anthropic), based on the
provider's models.dev entry::

    use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
    use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;

    $platform = AnthropicFactory::createPlatform(
        apiKey: $_ENV['ANTHROPIC_API_KEY'],
        modelCatalog: new ModelCatalog('anthropic'),
    );

This is the key feature of the bridge: your model definitions stay current independently
of the Symfony AI release cycle, refreshed with ``composer update symfony/models-dev``.

Multiple Providers in one Platform
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Compose providers from the regular bridges — each backed by a models.dev ``ModelCatalog`` —
into a single ``Platform``. ``ProviderRegistry`` resolves the base URL for OpenAI-compatible
providers (including the ones models.dev does not publish directly)::

    use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
    use Symfony\AI\Platform\Bridge\Generic\Factory as GenericFactory;
    use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;
    use Symfony\AI\Platform\Bridge\ModelsDev\ProviderRegistry;
    use Symfony\AI\Platform\Platform;

    $registry = new ProviderRegistry();

    $platform = new Platform([
        GenericFactory::createProvider(
            baseUrl: $registry->getApiBaseUrl('deepseek'),
            apiKey: $_ENV['DEEPSEEK_API_KEY'],
            modelCatalog: new ModelCatalog('deepseek'),
            name: 'deepseek',
        ),
        AnthropicFactory::createProvider(
            apiKey: $_ENV['ANTHROPIC_API_KEY'],
            modelCatalog: new ModelCatalog('anthropic'),
        ),
    ]);

    $platform->invoke('deepseek-chat', $messages);     // → deepseek
    $platform->invoke('claude-haiku-4-5', $messages);  // → anthropic

Routing is a core Platform feature, the bridge only supplies the providers and catalogs. See
the :doc:`Platform component <../platform>` documentation (*Providers and Multi-Provider
Platforms*) for the routing mechanics and custom strategies.

By default a model id resolves to the first provider (in array order) whose catalog knows it.
When several providers expose the *same* model id (e.g. a first-party provider and an
aggregator that re-lists it), order the array so the preferred provider comes first.

Embeddings
~~~~~~~~~~

Embedding models are detected automatically and wired to the matching embeddings model
class. Use them like any other embedding model::

    use Symfony\AI\Platform\Bridge\Generic\Factory as GenericFactory;
    use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;
    use Symfony\AI\Platform\Bridge\ModelsDev\ProviderRegistry;

    $registry = new ProviderRegistry();

    $platform = GenericFactory::createPlatform(
        baseUrl: $registry->getApiBaseUrl('openai'),
        apiKey: $_ENV['OPENAI_API_KEY'],
        modelCatalog: new ModelCatalog('openai'),
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

    foreach ($result->asTextStream() as $delta) {
        echo $delta;
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

Discovering Providers
~~~~~~~~~~~~~~~~~~~~~

The models.dev registry covers many providers. ``ProviderRegistry`` gives you access to
their metadata, e.g. to list everything available and resolve their API base URL::

    use Symfony\AI\Platform\Bridge\ModelsDev\ProviderRegistry;

    $registry = new ProviderRegistry();

    $registry->has('deepseek');                 // true
    $registry->getProviderName('deepseek');     // "DeepSeek"

    foreach ($registry->getProviderIds() as $id) {
        // null when no base URL is known (must then be passed explicitly)
        $url = $registry->getApiBaseUrl($id) ?? '(manual)';
        echo sprintf("%s: %s\n", $id, $url);
    }

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
                    base_url: 'https://api.groq.com/openai'
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

Resources
---------

 * `Contributing <https://symfony.com/doc/current/contributing/index.html>`_
 * `Report issues <https://github.com/symfony/ai/issues>`_ and
   `send Pull Requests <https://github.com/symfony/ai/pulls>`_
   in the `main Symfony AI repository <https://github.com/symfony/ai>`_

.. _models.dev: https://models.dev/

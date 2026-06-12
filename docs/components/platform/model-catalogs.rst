Working with Model Catalogs
===========================

Every Symfony AI platform resolves a model name like ``gpt-5-mini`` into a
:class:`Symfony\\AI\\Platform\\Model` object — a model name, its
:class:`Symfony\\AI\\Platform\\Capability` set, and default options. The component that
performs this lookup is the **model catalog**. Each bridge ships its own catalog, hand-curated
for the models that provider was known to offer when the bridge was released.

That works well until a provider ships a model your installed bridge has never heard of. Because
the catalog is bundled with the bridge, its **lifecycle is tied to the Symfony AI release cycle**:
a freshly released model — or a local model served by Ollama or LM Studio — is simply not in the
catalog yet, and invoking it fails. This page describes the ways to deal with that, from a
one-off per-call override to a catalog that refreshes on its own schedule.

Understanding the Catalog Lifecycle
-----------------------------------

A model catalog is just an implementation of
:class:`Symfony\\AI\\Platform\\ModelCatalog\\ModelCatalogInterface`. When you call
``$platform->invoke('gpt-5-mini', $messages)``, the platform asks its catalog for the model
matching that name and uses the capabilities and options the catalog returns.

There is no single "right" way to keep that catalog current — it depends on how much you want to
own. The approaches below trade off control against convenience:

#. **Configure it yourself** — add or override individual models, or pass a fully defined model per
   call and skip the catalog entirely. Best when you have a handful of custom or local models, or
   want to source model definitions from somewhere else (a database, a feature flag, ...).
#. **Use a continuously updated catalog** — back the catalog with the community
   `models.dev`_ registry, shipped as a Composer package you update independently of the
   framework. Best when you want broad, fresh coverage without curating models by hand.

You can mix them: a models.dev-backed catalog for the long tail, plus a per-call override or a
bundle entry for the one model that is not in any registry yet.

Approach 1: Pass a Model Instance Per Call
------------------------------------------

The most direct escape hatch is to hand a fully defined model instance to
``Platform::invoke()`` instead of a name. This bypasses the catalog lookup completely, so the
model never has to exist in any catalog::

    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
    use Symfony\AI\Platform\Capability;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $model = new Gpt('gpt-newest', [
        Capability::INPUT_MESSAGES,
        Capability::OUTPUT_TEXT,
        Capability::TOOL_CALLING,
    ], ['temperature' => 0.5]);

    $result = $platform->invoke($model, new MessageBag(
        Message::ofUser('What is the Symfony framework?'),
    ));

A ``string`` keeps the usual catalog-based routing; a ``Model`` instance bypasses it. This is the
foundation for decoupling from the catalog entirely — if your model definitions live in a
database or are toggled by configuration, build the instance from that source and pass it in.

.. note::

    You must pass a **bridge-specific** model subclass (``Gpt``, ``Claude``, ``Gemini``, ...), not
    the base :class:`Symfony\\AI\\Platform\\Model`. Model clients, result converters, and contract
    normalizers dispatch on the concrete class, so a bare ``Model`` has no client to handle it. The
    platform routes the instance to the first provider whose model clients accept it.

Approach 2: Add Models via the AI Bundle Configuration
------------------------------------------------------

In a Symfony application using the AI Bundle, you can extend a platform's built-in catalog
declaratively with the ``model`` configuration key — no per-call override and no custom catalog
service. This is the natural fit for local models (`LM Studio`_, `Ollama`_) or a model that has
not been added to the bridge yet:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            lmstudio:
                host_url: '%env(LMSTUDIO_HOST_URL)%'

        model:
            lmstudio:
                qwen3-coder-next:
                    class: 'Symfony\AI\Platform\Bridge\Generic\CompletionsModel'
                    capabilities:
                        - !php/const Symfony\AI\Platform\Capability::INPUT_MESSAGES
                        - !php/const Symfony\AI\Platform\Capability::OUTPUT_TEXT
                        - !php/const Symfony\AI\Platform\Capability::OUTPUT_STREAMING
                        - !php/const Symfony\AI\Platform\Capability::TOOL_CALLING

        agent:
            coder:
                platform: 'ai.platform.lmstudio'
                model: 'qwen3-coder-next'

Models are keyed by platform name. For each one you provide the model ``class`` (it must extend
:class:`Symfony\\AI\\Platform\\Model`; for generic OpenAI-compatible platforms use
:class:`Symfony\\AI\\Platform\\Bridge\\Generic\\CompletionsModel` for chat models or
:class:`Symfony\\AI\\Platform\\Bridge\\Generic\\EmbeddingsModel` for embeddings) and the list of
``capabilities`` it supports.

The configured models are merged into the built-in catalog and take precedence over models with
the same name, so the same key also lets you override the capabilities of an existing model.

Approach 3: Use a Continuously Updated Catalog
----------------------------------------------

Configuring models one by one does not scale if you want broad, always-current coverage. The
models.dev bridge solves this by replacing a bridge's bundled catalog with one sourced from the
community `models.dev`_ registry, shipped as the standalone ``symfony/models-dev`` Composer
package — a daily snapshot of providers, models, capabilities, and pricing.

The key benefit is that the **catalog lifecycle is decoupled from the framework release cycle**.
Newly released models arrive with ``composer update symfony/models-dev``, without bumping
``symfony/ai-platform`` or editing any catalog by hand.

Install the bridge and the data package::

    composer require symfony/ai-models-dev-platform symfony/models-dev

The bridge provides a ``ModelCatalog`` that reads the models.dev data for a given provider and
drops into the matching bridge in place of its bundled catalog. For any OpenAI-compatible
provider, pair it with the Generic bridge::

    use Symfony\AI\Platform\Bridge\Generic\Factory as GenericFactory;
    use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;

    $platform = GenericFactory::createPlatform(
        baseUrl: 'https://api.deepseek.com',
        apiKey: $_ENV['DEEPSEEK_API_KEY'],
        modelCatalog: new ModelCatalog('deepseek'),
    );

    $result = $platform->invoke('deepseek-chat', $messages);

For a provider that needs a specialized bridge, pair the catalog with that bridge. The catalog
already carries the model class that bridge expects (e.g. ``Claude`` for Anthropic), based on the
provider's models.dev entry::

    use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
    use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;

    $platform = AnthropicFactory::createPlatform(
        apiKey: $_ENV['ANTHROPIC_API_KEY'],
        modelCatalog: new ModelCatalog('anthropic'),
    );

The :doc:`models-dev` reference covers the bridge in full, including its ``Factory``, embeddings,
streaming, and bundle configuration.

Combining Providers in One Platform
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Because each provider is just a regular bridge backed by a models.dev catalog, you can compose
several into a single ``Platform`` and let it route by model name. ``ProviderRegistry`` resolves
the API base URL for OpenAI-compatible providers, including ones models.dev does not publish
directly::

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

A model id resolves to the first provider (in array order) whose catalog knows it. When several
providers expose the *same* id, order the array so the preferred one comes first.

Choosing an Approach
--------------------

There is deliberately no single default; pick the smallest tool that covers your case:

* **One or two custom/local models** — Approach 2 (bundle config) keeps everything declarative, or
  Approach 1 if you are not using the bundle.
* **Model definitions from an external source** (database, feature flags, an admin UI) — Approach 1,
  building the ``Model`` instance from that source and bypassing the catalog.
* **Broad, always-current coverage across many providers** — Approach 3, refreshed on its own
  cadence with ``composer update symfony/models-dev``.

These compose: a models.dev-backed catalog handles the long tail while a bundle entry or per-call
instance covers the one model no registry has yet.

See Also
--------

* :doc:`../platform` - Platform component, model catalogs, and passing model instances
* :doc:`models-dev` - models.dev bridge reference
* :doc:`../../bundles/ai-bundle` - AI Bundle configuration, including adding models to a catalog
* `models.dev`_ - The community model registry behind the bridge

.. _`models.dev`: https://models.dev/
.. _`LM Studio`: https://lmstudio.ai/
.. _`Ollama`: https://ollama.com/

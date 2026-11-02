AWS Bedrock
===========

AWS exposes its models through two different inference engines, and the
``symfony/ai-bedrock-platform`` bridge covers both.

The original one is the ``InvokeModel`` API, reached through the AWS SDK. It serves Amazon's Nova
models as well as Anthropic's Claude and Meta's Llama, and is configured with an
``AsyncAws\BedrockRuntime\BedrockRuntimeClient``, so credentials, region and retries follow the
usual AWS conventions::

    use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
    use Symfony\AI\Platform\Bridge\Bedrock\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform(new BedrockRuntimeClient(['region' => 'us-east-1']));

    $messages = new MessageBag(
        Message::forSystem('You are a pirate and you write funny.'),
        Message::ofUser('What is the Symfony framework?'),
    );
    $result = $platform->invoke('nova-pro', $messages);

    echo $result->asText();

The second one is `Bedrock Mantle`_, which speaks the OpenAI-compatible Chat Completions and
Responses protocols as well as the Anthropic Messages API, and serves the open-weight models
alongside Claude. Each protocol is a separate route on the same host, selected with the ``api``
argument of ``Mantle\Factory``::

    use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Factory;

    $completions = Factory::createPlatform(apiKey: 'ABSK...', region: 'us-east-1');
    $responses = Factory::createPlatform(apiKey: 'ABSK...', region: 'us-east-1', api: 'responses');
    $messages = Factory::createPlatform(apiKey: 'ABSK...', region: 'us-east-1', api: 'messages');

A platform serves exactly one route and uses the model catalog of that route. To reach models of
more than one route from a single platform, combine the providers::

    use Symfony\AI\Platform\Platform;

    $platform = new Platform([
        Factory::createProvider(apiKey: 'ABSK...', region: 'us-east-1', api: 'messages'),
        Factory::createProvider(apiKey: 'ABSK...', region: 'us-east-1', api: 'responses'),
    ]);

The platform then routes each model name to the first provider whose catalog knows it.

Authentication
--------------

Every Mantle route accepts a Bedrock API key, which is sent as a bearer token::

    $platform = Factory::createPlatform(apiKey: 'ABSK...', region: 'us-east-1');

When no API key is given, requests are signed with AWS SigV4 using the standard credential chain,
so the usual ``AWS_ACCESS_KEY_ID``/``AWS_SECRET_ACCESS_KEY`` environment variables, instance roles
or profiles apply. A custom ``AsyncAws\Core\Credentials\CredentialProvider`` can be passed as
well::

    $platform = Factory::createPlatform(region: 'us-east-1');

Bedrock API keys are scoped to the region they were created in, so the key and the ``region``
argument have to agree.

Chat Completions
----------------

The default route reuses the wire protocol of the ``symfony/ai-generic-platform`` bridge, which it
requires::

    use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform(apiKey: 'ABSK...', region: 'us-east-1');

    $messages = new MessageBag(
        Message::forSystem('You are a pirate and you write funny.'),
        Message::ofUser('What is the Symfony framework?'),
    );
    $result = $platform->invoke('openai.gpt-oss-120b', $messages);

    echo $result->asText();

Responses
---------

``api: 'responses'`` reuses the wire protocol of the ``symfony/ai-open-responses-platform``
bridge. That package is only required when using this route::

    $platform = Factory::createPlatform(apiKey: 'ABSK...', region: 'us-east-1', api: 'responses');

    $result = $platform->invoke('google.gemma-4-31b', $messages);

The Gemma family reports a reasoning trace, which the endpoint only emits when the request asks
for it through the ``reasoning`` option.

Anthropic Messages
------------------

``api: 'messages'`` serves Claude through the Anthropic-compatible Messages API. It reuses the
contract and result converter of the ``symfony/ai-anthropic-platform`` bridge, which the Bedrock
bridge already requires, so this route needs no optional package::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform(apiKey: 'ABSK...', region: 'us-east-1', api: 'messages');

    $messages = new MessageBag(
        Message::forSystem('You are a concise and helpful assistant.'),
        Message::ofUser('Explain the Symfony framework in one sentence.'),
    );
    $result = $platform->invoke('anthropic.claude-haiku-4-5', $messages);

    echo $result->asText();

Prompt caching is on by default: the ``cacheRetention`` argument accepts ``none``, ``short``
(default) or ``long`` and controls the ``cache_control`` markers injected into the system prompt,
the messages and the tool definitions. A ``workspace`` can be passed to scope requests to a Bedrock
Mantle workspace, which is sent as the ``anthropic-workspace`` header::

    $platform = Factory::createPlatform(
        apiKey: 'ABSK...',
        region: 'us-east-1',
        api: 'messages',
        cacheRetention: 'long',
        workspace: 'proj_example',
    );

Both arguments are rejected with an ``InvalidArgumentException`` on the other two routes, rather
than being silently ignored.

Unlike the other two routes, this one does not support structured output: AWS rejects
``output_config.format`` on the Mantle Messages path, so the bridge fails fast with an
``InvalidArgumentException`` rather than letting the request 400.

Model Catalogs
--------------

Each route ships a model catalog with the models verified against the endpoint. Additional models
can be registered by passing them to the catalog constructor, see :doc:`model-catalogs`.

Mind that Mantle partitions its OpenAI-compatible surface across two path prefixes, and each one
rejects the other's models with a ``400``: the gpt-oss and qwen families answer on ``/v1/...``,
the Gemma family on ``/openai/v1/...``. The catalogs follow that split - Gemma is registered on
the Responses route, the open-weight models on Chat Completions - so the defaults need no further
thought. Registering a model of the other family means passing the matching ``path`` as well::

    $platform = Factory::createPlatform(
        apiKey: 'ABSK...',
        region: 'us-east-1',
        modelCatalog: new ModelCatalog(['google.gemma-4-31b' => [
            'class' => CompletionsModel::class,
            'capabilities' => [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT],
        ]]),
        path: '/openai/v1/chat/completions',
    );

Code Examples
-------------

* `Bedrock Mantle Chat`_
* `Bedrock Mantle Chat with SigV4`_
* `Bedrock Mantle Streaming`_
* `Bedrock Mantle Tool Calling`_
* `Bedrock Mantle Responses`_
* `Bedrock Mantle Responses Streaming`_
* `Bedrock Mantle Responses Tool Calling`_
* `Bedrock Mantle Anthropic Messages`_
* `Bedrock Mantle Anthropic Messages Streaming`_

.. _`Bedrock Mantle`: https://docs.aws.amazon.com/bedrock/latest/userguide/bedrock-mantle.html
.. _`Bedrock Mantle Chat`: https://github.com/symfony/ai/blob/main/examples/bedrock/chat-mantle.php
.. _`Bedrock Mantle Chat with SigV4`: https://github.com/symfony/ai/blob/main/examples/bedrock/chat-mantle-sigv4.php
.. _`Bedrock Mantle Responses`: https://github.com/symfony/ai/blob/main/examples/bedrock/responses-mantle.php
.. _`Bedrock Mantle Responses Streaming`: https://github.com/symfony/ai/blob/main/examples/bedrock/responses-stream-mantle.php
.. _`Bedrock Mantle Responses Tool Calling`: https://github.com/symfony/ai/blob/main/examples/bedrock/responses-toolcall-mantle.php
.. _`Bedrock Mantle Anthropic Messages`: https://github.com/symfony/ai/blob/main/examples/bedrock/messages-mantle.php
.. _`Bedrock Mantle Anthropic Messages Streaming`: https://github.com/symfony/ai/blob/main/examples/bedrock/messages-stream-mantle.php
.. _`Bedrock Mantle Streaming`: https://github.com/symfony/ai/blob/main/examples/bedrock/stream-mantle.php
.. _`Bedrock Mantle Tool Calling`: https://github.com/symfony/ai/blob/main/examples/bedrock/toolcall-mantle.php

Mock Provider
=============

A routing-aware, in-memory **provider** for tests. It registers like a real bridge,
participates in model routing, returns scripted responses of **any** result type
(text, structured object, stream, tool calls, embeddings), and records every call.

Mock provider vs. `InMemoryPlatform`
------------------------------------

`Symfony\AI\Platform\Test\InMemoryPlatform` is a *platform-level* fake: it replaces the whole
`Platform`, ignores routing, has no model catalog, and returns text only. Reach for it when a
unit test injects a `PlatformInterface` directly.

The **fake provider** is *provider-level* and complementary. Use it when a test must:

 * go through real DI / routing (functional tests);
 * coexist with real providers and prove routing picks the fake (multi-provider tests);
 * return non-text results (`ObjectResult`, `StreamResult`, `ToolCallResult`, `VectorResult`);
 * assert on exactly what the platform sent (recorded call history via `getCalls()`).

Scripting forms
---------------

`MockModelClient` / `Factory` accept one of three response forms:

```php
use Symfony\AI\Platform\Mock\Factory;
use Symfony\AI\Platform\Result\ObjectResult;

// 1. string — every call returns a TextResult wrapping it
$platform = Factory::createPlatform('Hello world');
$platform->invoke('any-model', 'question')->asText(); // 'Hello world'

// 2. map keyed by model name — per-model ResultInterface|string
$platform = Factory::createPlatform([
    'gpt-4o-mini' => 'cheap answer',
    'gpt-4o'      => 'expensive answer',
]);

// 3. closure — full control; branch on payload/options
$platform = Factory::createPlatform(
    static fn ($model, $payload, $options) => isset($options['response_format'])
        ? new ObjectResult(['ok' => true])
        : 'plain text',
);
```

Pass a `Symfony\AI\Platform\Mock\ModelCatalog` (instead of the default `FallbackModelCatalog`) to
register specific models with specific capabilities and gate which names route to the fake.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)

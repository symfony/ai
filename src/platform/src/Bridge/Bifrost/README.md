Bifrost Platform
================

[Bifrost](https://docs.getbifrost.ai/) platform bridge for Symfony AI.

Bifrost is an open-source LLM gateway that provides a unified, OpenAI-compatible
HTTP interface to many AI providers (OpenAI, Anthropic, AWS Bedrock, Google
Gemini, Cohere, Mistral, …). It is typically self-hosted and addressed via the
`provider/model` notation (e.g. `openai/gpt-4o-mini`, `anthropic/claude-3-opus`).

This bridge supports:

 * Chat completions (`POST /v1/chat/completions`) — reuses the Generic bridge
 * Embeddings (`POST /v1/embeddings`) — reuses the Generic bridge
 * Text-to-Speech (`POST /v1/audio/speech`)
 * Speech-to-Text (`POST /v1/audio/transcriptions` and `/v1/audio/translations`)
 * Image generation (`POST /v1/images/generations`)
 * Dynamic model catalogue (`GET /v1/models`) loaded lazily

Configuration
-------------

The bridge accepts either an `$endpoint` (the Bifrost base URL) or a
pre-configured `HttpClientInterface` (typically a `ScopingHttpClient` declared in
`framework.http_client.scoped_clients`). When `$endpoint` is `null`, all paths
are issued as relative URLs and the injected HTTP client is expected to provide
the base URI.

```php
use Symfony\AI\Platform\Bridge\Bifrost\Factory;

$platform = Factory::createPlatform('sk-bf-...', 'http://localhost:8080');

$response = $platform->invoke('openai/gpt-4o-mini', 'Hello, Bifrost!');
```

Bifrost Documentation
---------------------

 * [Quickstart](https://docs.getbifrost.ai/quickstart)
 * [Chat completions](https://docs.getbifrost.ai/api-reference/chat-completions)
 * [Embeddings](https://docs.getbifrost.ai/api-reference/embeddings)
 * [Audio (TTS / STT)](https://docs.getbifrost.ai/api-reference/audio)
 * [Image generation](https://docs.getbifrost.ai/api-reference/images)
 * [Model catalogue](https://docs.getbifrost.ai/api-reference/models)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)

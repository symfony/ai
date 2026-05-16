CHANGELOG
=========

0.8
---

 * Add autoconfiguration for `SchemaProviderInterface` (tag `ai.platform.json_schema.provider`) and inject the tagged iterator into `SchemaAttributeDescriber` to back the `#[Schema(provider: ...)]` runtime fragments
 * Add `SchemaProviderValidationPass` compiler pass that validates `#[Schema(provider: ...)]` references on tagged tools at container build time
 * [BC BREAK] Rename service ID `ai.agent.response_format_factory` to `ai.platform.response_format_factory`
 * The `collection` option for `ChromaDb` is now optional.
 * Update `DataCollector` to use `getCalls()` and `getResultCache()` getter methods on Traceable* classes

0.7
---

 * [BC BREAK] Move `TraceablePlatform` to `Symfony\AI\Platform\TraceablePlatform`
 * [BC BREAK] Move `TraceableAgent` to `Symfony\AI\Agent\TraceableAgent`
 * [BC BREAK] Move `TraceableToolbox` to `Symfony\AI\Agent\Toolbox\TraceableToolbox`
 * [BC BREAK] Move `TraceableStore` to `Symfony\AI\Store\TraceableStore`
 * [BC BREAK] Move `TraceableChat` to `Symfony\AI\Chat\TraceableChat`
 * [BC BREAK] Move `TraceableMessageStore` to `Symfony\AI\Chat\TraceableMessageStore`
 * The `api_catalog` option for `Ollama` has been removed as the catalog is now automatically fetched from the Ollama server
 * The `api_key` option for `Ollama` is now `null` by default to allow the usage of a `ScopingHttpClient`
 * The `endpoint` option for `Ollama` is now `null` by default to allow the usage of a `ScopingHttpClient`
 * The `api_catalog` option for `ElevenLabs` has been removed as the catalog is now automatically fetched from the ElevenLabs servers
 * The `api_key` option for `ElevenLabs` is now `null` by default to allow the usage of a `ScopingHttpClient`
 * The `endpoint` option for `Azure` store is now `null` by default to allow the usage of a `ScopingHttpClient`
 * The `api_key` option for `Azure` store is now `null` by default to allow the usage of a `ScopingHttpClient`
 * The `api_version` option for `Azure` store is now `null` by default to allow the usage of a `ScopingHttpClient`
 * The `vector_field` option for `Azure` store is now `vector` by default
 * Add support for `ScopingHttpClient` usage in `AzureSearch` store
 * Add support for `ScopingHttpClient` usage in `Weaviate` store
 * Validate tool call arguments using `symfony/validator` when available
 * Add `speech` configuration node for automatic `SpeechAgent` decoration with TTS/STT support
 * The `strategy` option for `Cache` store is now `cosine` by default
 * The `DistanceCalculator` is no longer a service when using `Cache` store

0.6
---

 * Move debug service decorating to compiler pass to cover user-defined services
 * Add `TraceableAgent`
 * Add `TraceableStore`
 * Add `setup_options` configuration for PostgreSQL store to pass extra fields to `ai:store:setup`
 * Add support for VertexAI global endpoint with API key authentication (no `location`/`project_id` required)
 * The `api_key` option for `ElevenLabs` is not required anymore if a `ScopedHttpClient` is used in `http_client` option

0.5
---

 * Add `setup_options` configuration for MongoDB store to pass extra fields to `ai:store:setup`
 * Add `ovh` support for platform configuration

0.4
---

 * Add `chats` data from `DataCollector` to the `data_collector.html.twig` template
 * [BC BREAK] Rename service ID prefix `ai.toolbox.{agent}.agent_wrapper.` to `ai.toolbox.{agent}.subagent.`
 * Add support for `DocumentIndexer` when no loader is configured for an indexer
 * [BC BREAK] The `host_url` configuration key for `Ollama` has been renamed `endpoint`
 * Add `ResetInterface` support to `TraceableChat`, `TraceableMessageStore`, `TraceablePlatform` and `TraceableToolbox` to clear collected data between requests

0.2
---

 * [BC BREAK] Remove `Agent` and `MultiAgent` suffixes from injection aliases.
 * Add `http_client` option to VertexAI platform configuration
 * Add `bedrock` support for platform configuration

0.1
---

 * Add the bundle

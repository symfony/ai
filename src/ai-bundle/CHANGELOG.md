CHANGELOG
=========

1.0
---

 * Add the `model_catalog` platform option with `service`, `models`, `api` and `cache` options to use a custom catalog, add models, fetch the models from the provider API and cache the model lookups; a string is short for `service`
 * [BC BREAK] Replace the `setup_options` of the `mariadb`, `mongodb`, `pinecone`, `postgres`, `redis` and `s3vectors` stores by store keys: `dimensions`, `metric`, `cloud`, `region`, `vector_type`, `index_method`, `index_opclass` and `index_fields`
 * [BC BREAK] Remove the `setup_options.force_database_creation` option of the `surrealdb` store, use `ai:store:setup --option forceDatabaseCreation=true` instead
 * [BC BREAK] Rename the `similarity` (`elasticsearch`, `manticoresearch`, `mongodb`), `metric_type` (`milvus`), `space_type` (`opensearch`), `strategy` (`surrealdb`) and `distance` (`mariadb`, `neo4j`, `postgres`, `qdrant`, `redis`, `sqlite`) store keys to `metric`, and the `vector_dimension` key of the `sqlite` and `supabase` stores to `dimensions`
 * [BC BREAK] Require store and message store names to be unique across all types, and `failover` platform names to differ from other platform names
 * [BC BREAK] Remove the type-prefixed `StoreInterface` and `MessageStoreInterface` aliases, e.g. `StoreInterface $chromadbDocuments`
 * Add a `doctrine` store, keeping vectors in an `#[Embedding]` field of a Doctrine entity, and embedding the entity on flush and through `ai:store:index` when a `vectorizer` is configured
 * [BC BREAK] Name stores by their configured name in the `ai:store:setup`, `ai:store:drop` and `ai:store:clear` commands instead of their service id
 * [BC BREAK] Remove the root-level `ai.model` option in favor of the `models` option of the platform's `model_catalog`
 * [BC BREAK] Remove the `ai.platform.model_catalog.amazeeai` service
 * [BC BREAK] Remove the unused `ai.platform.model_catalog.aimlapi`, `ai.platform.model_catalog.meta` and `ai.platform.model_catalog.replicate` services
 * [BC BREAK] Remove the `api_catalog` option of the `huggingface` platform in favor of the `api` option of its `model_catalog`
 * Add the `message_store` agent option, continuing and saving the conversations of the agent by their ID
 * [BC BREAK] Configured message stores are the stores of the Agent component, implementing `Symfony\AI\Agent\MessageStore\MessageStoreInterface`
 * [BC BREAK] Remove the `chat` option and the chats of the profiler together with the Chat component, use the `message_store` agent option instead
 * [BC BREAK] Remove the `identifier` option of the `memory` message store
 * [BC BREAK] Remove the `exclude_tool_messages` agent option, tool messages never reach the caller's `MessageBag`
 * Add the `mirror_to` option to the `doctrine` store, mirroring the entity into another store and registering an entity retriever

0.15
----

 * Tag the MCP toolboxes of `mcp_server` tool entries with `kernel.reset`, so long-running workers refresh the tool list of a remote MCP server between requests and messages
 * Add `setup_options` configuration for Redis store to set the `vector_size` used by `ai:store:setup`
 * Add `setup_options` configuration for S3 Vectors store to set the `dimension` and `distance_metric` used by `ai:store:setup`
 * Register a `#[Target]` alias for every configured platform, including the failover instance name
 * Add `dimensions` and `similarity` configuration for MongoDB store, used by `ai:store:setup` to create the vector index
 * Add `setup_options` configuration for Pinecone store to set the `dimension`, `metric`, `cloud` and `region` used by `ai:store:setup`
 * Add `setup_options.force_database_creation` configuration for SurrealDB store to create the namespace and database in `ai:store:setup`
 * Add `api_catalog` option to the `huggingface` platform to resolve models through the Hugging Face Hub API
 * [BC BREAK] Change the default `provider` of the `huggingface` platform to `null`, letting Hugging Face pick the provider for chat completion

0.14
----

 * Add `edenai` platform configuration for the Eden AI bridge, including the `ai.platform.job_client.edenai` service for its asynchronous speech-to-text jobs
 * Add `typesafe` platform configuration for the TypeSafe bridge
 * Register the OpenAI job client as `ai.platform.job_client.openai`, tagged and autowired by argument name, so a stored batch handle can be resolved from a worker
 * Show agent calls, platform invocations and tool executions in the performance timeline of the profiler
 * Add support for configuring a tool execution strategy per agent via the `execution_strategy` option under `tools` (`sequential`, `fiber`, or a custom service ID)
 * Add an `mcp_server` tool entry to `ai.agent.<name>.tools`, exposing the tools of a remote MCP server to an agent by referencing a connection configured under `mcp.clients`
 * Add an `api` option to the `bedrock` platform configuration, selecting the SDK-based InvokeModel API or one of the Bedrock Mantle routes (Chat Completions, Responses, Anthropic Messages)
 * Add `fireworks` platform configuration for OpenAI-compatible chat, embeddings and reranking
 * Add `together` platform configuration for chat, embeddings, image generation, text-to-speech, speech-to-text and reranking
 * Add `venice` platform configuration for chat, embeddings, image generation and editing, text-to-speech, speech-to-text and video generation
 * Add `higgsfield` platform configuration for asynchronous image and video generation
 * Register `ai.platform.job_runner` (autowired as `Platform\Job\JobRunner`) using the application clock and, for a platform running asynchronous jobs, its `ai.platform.job_client.<name>` client, built by the bridge factory and tagged `ai.platform.job_client`; render an asynchronous job in the profiler as the handle it carries instead of as a result
 * Allow configuring the `elasticsearch` store with a pre-scoped `http_client` instead of an `endpoint`
 * Build the `clickhouse`, `manticoresearch`, `milvus`, `neo4j`, `opensearch` and `supabase` stores through their bridge `StoreFactory`, so a pre-scoped `http_client` can replace the endpoint and credentials; either the endpoint or `http_client` must be configured, credentials require the endpoint, and a custom `http_client` is scoped to the endpoint when both are set
 * Add an `http_client` option to the `manticoresearch`, `milvus` and `neo4j` stores

0.13
----

 * Add support for template variables in file prompts
 * Add an `embedding_function` option to the `chromadb` store, referencing a service implementing `Codewithkyrian\ChromaDB\Embeddings\EmbeddingFunction`, so a bundle-configured store can serve a `TextQuery`

0.11
----

 * Pass the `serializer` to `TemplateRendererListener` so objects can be used as `template_vars`
 * Add `AiAssertionsTrait` for functional tests based on Profiler data
 * Register the `ai:store:clear` command to remove all documents from a store
 * Add `minimax` platform configuration for the MiniMax bridge
 * Autoconfigure `SchemaProviderInterface` implementations so they can be referenced from `#[Schema(provider: ...)]`, and validate those references on tools at container build time
 * Add support for `ScopingHttpClient` usage in `Typesense` store via `http_client` option
 * Wire `Typesense\StoreFactory` from `AiBundle`
 * Add support for `ScopingHttpClient` usage in `Cloudflare` store via `http_client` option
 * Add support for `ScopingHttpClient` usage in `SurrealDB` store via `http_client` option
 * Register `StringToMessageBagListener` in DI to enable string-to-MessageBag upcasting by default
 * Command `ai:platform:invoke` now adds choices when model is not provided as argument

0.10
----

 * [BC BREAK] Agent tools are now opt-in: when the `tools` option is not configured (or set to `null` or
   an empty list), no toolbox is registered for the agent. Set `tools: true` to restore the previous
   behavior of injecting all services tagged with `ai.tool`
 * Throw an exception when `prompt.include_tools` is enabled for an agent without configured tools
 * [BC BREAK] Cap an agent's tool-calling loop at `50` iterations by default (following the `AgentProcessor` change). Configure the limit per agent via the new `max_tool_calls` option, or set it to `null` to restore the previous unbounded behaviour

0.9
---

 * Validate structured output using `symfony/validator` when available
 * Make `ai:platform:invoke` arguments optional and prompt for them interactively when missing
 * Visualize failed calls with `result_type: 'error'` in profiler
 * Add `openresponses` platform configuration for OpenAI Responses-compatible endpoints
 * Add support for `ScopingHttpClient` usage in `Meilisearch` store via `http_client` option
 * The `api_key` option for `Meilisearch` is now `null` by default to allow the usage of a `ScopingHttpClient`
 * The `endpoint` option for `Meilisearch` is now `null` by default to allow the usage of a `ScopingHttpClient`
 * Wire `Meilisearch\StoreFactory` from `AiBundle`
 * Add `lang` option for `Postgres`

0.8
---

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

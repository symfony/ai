CHANGELOG
=========

0.6
---

 * Move debug service decorating to compiler pass to cover user-defined services
 * Add `TraceableAgent`

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

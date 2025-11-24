CHANGELOG
=========
0.1
---

* Introduce the component
* Add support for external message stores:
   - Symfony Cache
   - Cloudflare
   - Doctrine
   - Meilisearch
   - MongoDb
   - Pogocache
   - Redis
   - SurrealDb
* Add streaming support to `ChatInterface::submit()`
   - Add `StreamableStoreInterface` which indicates `StoreInterface` implementation can be configured with streaming 
   - Add `AccumulatingStreamResult` wrapper class which adds accumulation logic & callback chaining to `StreamResult` implementations (can wrap both `Agent` and `Platform` variants) to return the full message once `Generator` is exhausted
   - Streamed responses now also create `AssistantMessage` & are added to `Store` in `Chat::submit()`
   - Bugfixed loss of metadata in `Chat::submit()`
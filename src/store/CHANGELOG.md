CHANGELOG
=========

0.7
---

 * Add `TraceableStore` profiler decorator moved from AI Bundle
 * Add `RstLoader` and `RstToctreeLoader` for loading RST files and following toctree directives
 * Add pre-query event dispatching for query enhancement before vectorization
 * Add platform-based `Reranker` for cross-encoder reranking via `PlatformInterface`
 * Add `CombinedStore` combining vector and text stores with Reciprocal Rank Fusion (RRF)
 * [BC BREAK] Add `?EventDispatcherInterface $eventDispatcher` as 3rd constructor parameter of `Retriever` (before `$logger`)
 * Add automatic text content preservation in `Vectorizer` metadata
 * Add batch processing in `DistanceCalculator` for local vector stores

0.6
---

 * Add `SummaryGeneratorTransformer` for generating LLM-based summaries of documents

0.4
---

 * Add `CsvLoader` for loading documents from CSV files
 * Add `StoreInterface::remove()` method
 * Add `SourceIndexer` for indexing from sources (file paths, URLs, etc.) using a `LoaderInterface`
 * Add `DocumentIndexer` for indexing documents directly without a loader
 * Add `ConfiguredSourceIndexer` decorator for pre-configuring default sources on `SourceIndexer`
 * [BC BREAK] Remove `Indexer` class - use `SourceIndexer` or `DocumentIndexer` instead
 * [BC BREAK] Change `IndexerInterface::index()` signature - input parameter is no longer nullable
 * [BC BREAK] Remove `Uuid` as possible type for `TextDocument::id` and `VectorDocument::id`, use `string` or `int` instead
 * [BC BREAK] `Symfony\AI\Store\Document\EmbeddableDocumentInterface::getId()` now returns `string|int` instead of `mixed`
 * [BC BREAK] Reduce visibility of `VectorDocument` and `RssItem` properties to `private` and add getters
 * Add `MarkdownLoader`
 * Add `JsonFileLoader`
 * Add `ResetInterface` support to in-memory store

0.3
---

 * Add support for more types (`int`, `string`) on `VectorDocument` and `TextDocument`
 * [BC BREAK] Store Bridges don't auto-cast the document `$id` property to `uuid` anymore

0.2
---

 * [BC BREAK] Change `StoreInterface::add()` from variadic to accept array and `VectorDocument`

0.1
---

 * Add the component

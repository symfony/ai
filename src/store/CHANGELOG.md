CHANGELOG
=========

0.4
---

 * Add `StoreInterface::remove()` method
 * Add `SourceIndexer` for indexing from sources (file paths, URLs, etc.) using a `LoaderInterface`
 * Add `DocumentIndexer` for indexing documents directly without a loader
 * Add `ConfiguredSourceIndexer` decorator for pre-configuring default sources on `SourceIndexer`
 * [BC BREAK] Remove `Indexer` class - use `SourceIndexer` or `DocumentIndexer` instead
 * [BC BREAK] Change `IndexerInterface::index()` signature - input parameter is no longer nullable
 * [BC BREAK] Remove `Uuid` as possible type for `TextDocument::id` and `VectorDocument::id`, use `string` or `int` instead
 * [BC BREAK] `Symfony\AI\Store\Document\EmbeddableDocumentInterface::getId()` now returns `string|int` instead of `mixed`
 * [BC BREAK] Reduce visibility of `VectorDocument` and `RssItem` properties to `private` and add getters

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

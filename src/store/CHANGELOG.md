CHANGELOG
=========

0.4
---

 * [BC BREAK] Make `Indexer` stateless - `$source` constructor parameter and `withSource()` method removed
 * Add `ConfiguredIndexer` decorator for pre-configuring default sources

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

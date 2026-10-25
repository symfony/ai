---
status: accepted
date: 2026-10-25
---

# 0004. Queries are typed objects negotiated through supports()

## Context and Problem Statement

Backends search in different ways: some only by vector similarity, some only by
keywords, some both, plus a mix of the two (hybrid). The original
`query(Vector $vector)` signature tied every store to vectors. It could not express
keyword or hybrid search, or stores such as ChromaDB that compute embeddings
themselves and take plain text (RFC #1562). An options array would leave each store to
guess what the caller meant.

## Decision

`StoreInterface::query()` takes a `Query\QueryInterface`, an empty marker interface with
three final implementations:

- `VectorQuery`: a Platform `VectorInterface`.
- `TextQuery`: one or more search texts, combined with OR.
- `HybridQuery`: a vector, texts and a `semanticRatio` between 0.0 and 1.0, checked in
  the constructor.

A store declares what it handles with `supports(string $queryClass)` and dispatches on
the query class. For any other type it throws `Exception\UnsupportedQueryTypeException`
(see the `match` in `InMemory\Store::query()`). Backend-specific settings such as limits
or filters stay in the `$options` array.

`Retriever` turns a plain string into the best query the store supports: `TextQuery`
without a vectorizer or without vector support, `HybridQuery` when supported, otherwise
`VectorQuery`. It dispatches `Event\PreQueryEvent` and `Event\PostQueryEvent` around the
query, for example for query rewriting before and reranking after
(`EventListener\RerankerListener`). `CombinedStore` adds hybrid search on top of one
vector store and one text store, using Reciprocal Rank Fusion.

## Consequences

- **Good:** a new search mode is a new query class; `StoreInterface` does not change.
- **Good:** callers can check support before querying instead of catching errors.
- **Trade-off:** dispatch happens at runtime; a store whose `supports()` and `query()`
  disagree fails only when queried.
- **Trade-off:** `UnsupportedQueryTypeException` extends `\RuntimeException` directly and
  does not implement `Exception\ExceptionInterface`, so a catch on that interface misses
  it.

## Alternatives Considered

- **One `Query` class built with `fromVector()`/`fromText()`**: proposed in RFC #1562,
  where hybrid would be implicit. The typed classes were chosen in #1570.
- **Filters and a query type enum on `QueryInterface`**: the first draft of #1570 had
  `getType()`, `getFilter()` and an `EqualFilter`. Removed before merge and left for a
  separate issue, so filters stay store-specific options for now.
- **Separate methods per search mode on `StoreInterface`**: not chosen, because every
  store would need every mode or throw from most of them.

## History

- 2024-08-25, 0a65c502 by Christopher Hertel: the first store contract, a vector-only `query(Vector $vector)`.
- 2025-11-29, #1026 by @OskarStark (Oskar Stark): added the `Retriever` service on top of a store and a vectorizer.
- 2026-02-16, #1570 by @wachterjohannes (Johannes Wachter): added `QueryInterface`, `VectorQuery`, `TextQuery`, `HybridQuery`, `supports()` and `UnsupportedQueryTypeException`; `Retriever` picks the query type.
- 2026-03-14, #1704 by @wachterjohannes (Johannes Wachter): added `CombinedStore` with Reciprocal Rank Fusion and the `RerankerListener`.
- 2026-03-15, #1745 by @wachterjohannes (Johannes Wachter): added `PreQueryEvent` so listeners can change the query before it runs.
- 2026-09-06, #2494 by @chr-hertel (Christopher Hertel): vector queries accept any `VectorInterface`, not only the final `Vector`.

## References

- `src/Query/QueryInterface.php`, `src/Query/VectorQuery.php`, `src/Query/TextQuery.php`,
  `src/Query/HybridQuery.php`.
- `src/StoreInterface.php`: `query()` and `supports()`.
- `src/InMemory/Store.php`: reference dispatch on query types.
- `src/Retriever.php`: query type selection and query events.
- `src/CombinedStore.php`, `src/EventListener/RerankerListener.php`.
- `src/Exception/UnsupportedQueryTypeException.php`, `src/Exception/ExceptionInterface.php`.

---
status: accepted
date: 2026-10-25
---

# 0004. Queries are typed objects negotiated through supports()

## Context and Problem Statement

Backends differ in how they search: some only do vector similarity, some only keyword
search, some both and a fused hybrid of the two. A single `query(Vector $vector)`
signature cannot express keyword or hybrid search, and an options array would leave
each store to guess what the caller meant.

## Decision

`StoreInterface::query()` takes a `Query\QueryInterface`, an empty marker interface with
three final implementations:

- `VectorQuery`: a Platform `VectorInterface`.
- `TextQuery`: one or more search texts, combined with OR.
- `HybridQuery`: a vector, texts and a `semanticRatio` between 0.0 and 1.0, validated in
  the constructor.

A store declares what it handles with `supports(string $queryClass)` and dispatches on
the query class, throwing `Exception\UnsupportedQueryTypeException` for any other type
(see the `match` in `InMemory\Store::query()`). Backend-specific knobs such as limits or
filters stay in the `$options` array.

`Retriever` turns a plain string into the best query the store supports: `TextQuery`
without a vectorizer or without vector support, `HybridQuery` when supported, otherwise
`VectorQuery`. It dispatches `Event\PreQueryEvent` and `Event\PostQueryEvent` around the
query, which `EventListener\RerankerListener` uses to rerank results. `CombinedStore`
builds hybrid support from a vector store and a text store using Reciprocal Rank Fusion.

## Consequences

- **Good:** new search modes can be added as new query classes without changing
  `StoreInterface`.
- **Good:** callers can probe capabilities before querying instead of catching errors.
- **Trade-off:** dispatch happens at runtime with `instanceof`/`match`; a store that
  forgets to keep `supports()` and `query()` in sync fails only when queried.
- **Trade-off:** `UnsupportedQueryTypeException` extends `\RuntimeException` directly and
  does not implement `Exception\ExceptionInterface`, unlike the other component
  exceptions, so a catch on that interface does not cover it.

## Alternatives Considered

- **Separate methods per search mode on `StoreInterface`**: not chosen because every
  store would need to implement every mode or throw from most of them.
- **A vector-only `query()` with options for text search**: not chosen because the
  intent of the call would be hidden in untyped options.

## References

- `src/Query/QueryInterface.php`, `src/Query/VectorQuery.php`, `src/Query/TextQuery.php`,
  `src/Query/HybridQuery.php`.
- `src/StoreInterface.php`: `query()` and `supports()`.
- `src/InMemory/Store.php`: reference dispatch on query types.
- `src/Retriever.php`: query type selection and query events.
- `src/CombinedStore.php`, `src/EventListener/RerankerListener.php`.
- `src/Exception/UnsupportedQueryTypeException.php`, `src/Exception/ExceptionInterface.php`.

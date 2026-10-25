---
status: accepted
date: 2026-10-25
---

# 0002. Separate input documents from vector documents

## Context and Problem Statement

A document has two states. First it is content that is loaded, filtered, split and
enriched. Later it is an embedding that a store saves and returns with a score. One
class for both would make the vector optional while preprocessing and the content
optional in the store, and every store would have to guard against half-built
documents.

## Decision

The component uses two interfaces in `src/Document/`:

- `DocumentInterface` is the document to index: `getId()`, `getContent()` (a `string`
  or an `object`) and `getMetadata()`. `TextDocument` is the shipped implementation. It
  is immutable (`withContent()` returns a new instance) and rejects empty content.
  User-land objects can implement the interface directly.
- `VectorDocumentInterface` is what a store accepts and returns: `getId()`,
  `getVector()` (a Platform `VectorInterface`), `getMetadata()`, `getScore()` and
  `withScore()`. Stores and rerankers set the score on query results only.
  `VectorDocument` is the shipped, immutable implementation.

Only a `VectorizerInterface` turns one into the other. Stores, retrievers and rerankers
are typed against `VectorDocumentInterface`, not the final `VectorDocument`, so a store
can return a richer object, for example one that knows the entity or file behind a hit
(#2495).

`Metadata` is an `\ArrayObject` shared by both. It reserves underscore-prefixed keys for
values the component itself uses: `_parent_id`, `_text`, `_source`, `_summary`, `_title`
and `_depth`. When `includeText` is enabled, `Vectorizer` stores the original text under
`_text`, so a store can run text search on it and a reranker can read it.

## Consequences

- **Good:** a store never receives a document without a vector, and preprocessing code
  never deals with vectors.
- **Good:** ids are `int|string`. Several bridges had assumed a `Uuid`; plain scalars map
  to every backend without that dependency (#1575).
- **Trade-off:** the text of a vector document is only available through metadata, so
  text and hybrid queries depend on `_text` (or a bridge-specific field) being filled.
- **Trade-off:** `Metadata` is a mutable `\ArrayObject`, so the document classes are
  immutable but their metadata is not.

## Alternatives Considered

- **One `Document` class with a nullable text and a nullable vector**: the original
  llm-chain design (with `fromText()`, `fromVector()`, `hasText()`). Replaced by
  `TextDocument` and `VectorDocument` in php-llm/llm-chain#110.
- **Typing stores against the final `VectorDocument`**: used until #2495 replaced it
  with the interface, so stores can return their own document objects.
- **Typing indexing against the final `TextDocument`**: replaced in #707 by an
  interface, to let users index their own objects.

## History

- 2024-10-04, php-llm/llm-chain#110 by @chr-hertel (Christopher Hertel): split the single `Document` into `TextDocument` and `VectorDocument`.
- 2025-10-06, #707 by @paulinevos (Pauline Vos): added `EmbeddableDocumentInterface` so user-land objects can be indexed, and opened ids to union types.
- 2026-02-13, #1575 by @chr-hertel (Christopher Hertel): dropped `Uuid` from document ids, leaving `int|string`, because stores had assumed a `Uuid`.
- 2026-04-10, #1921 by @chr-hertel (Christopher Hertel): made keeping the text under `_text` an opt-in `includeText` option of `Vectorizer`, to soften a behavior change.
- 2026-09-06, #2495 by @chr-hertel (Christopher Hertel): added `VectorDocumentInterface` and typed stores, retrievers and rerankers against it.
- 2026-10-24, #2706 by @chr-hertel (Christopher Hertel): renamed `EmbeddableDocumentInterface` to `DocumentInterface`, to pair it with `VectorDocumentInterface`.

## References

- `src/Document/DocumentInterface.php`, `src/Document/TextDocument.php`.
- `src/Document/VectorDocumentInterface.php`, `src/Document/VectorDocument.php`.
- `src/Document/Metadata.php`: reserved keys.
- `src/Document/Vectorizer.php`: the only bridge between the two states.
- `CHANGELOG.md`: 0.4, 0.14 and 0.16 entries.

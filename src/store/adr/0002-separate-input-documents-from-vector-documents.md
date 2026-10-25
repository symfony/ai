---
status: accepted
date: 2026-10-25
---

# 0002. Separate input documents from vector documents

## Context and Problem Statement

A document passes through two states: as content that is loaded, filtered, split and
enriched, and as an embedding that a store persists and returns with a similarity score.
Mixing both in one class would make the vector optional during preprocessing and the
content optional in the store, and every store would have to guard against
half-built documents.

## Decision

The component uses two interfaces in `src/Document/`:

- `DocumentInterface` is the document to index: `getId()`, `getContent()` (a `string`
  or an `object`) and `getMetadata()`. `TextDocument` is the shipped implementation; it
  is immutable (`withContent()` returns a new instance) and rejects empty content.
- `VectorDocumentInterface` is what a store accepts and returns: `getId()`,
  `getVector()` (a Platform `VectorInterface`), `getMetadata()`, `getScore()` and
  `withScore()`. `VectorDocument` is the shipped, immutable implementation.

Only a `VectorizerInterface` turns one into the other. `StoreInterface`, retrievers and
rerankers are typed against `VectorDocumentInterface`, not the final `VectorDocument`, so
a store may return richer objects (CHANGELOG 0.14).

`Metadata` is an `\ArrayObject` shared by both. It reserves underscore-prefixed keys for
values the component itself relies on: `_parent_id`, `_text`, `_source`, `_summary`,
`_title` and `_depth`. `Vectorizer` stores the original text under `_text` when
`includeText` is enabled, so a text-capable store can search it.

## Consequences

- **Good:** a store never receives a document without a vector, and preprocessing code
  never has to deal with vectors.
- **Good:** ids are restricted to `int|string`, so every bridge can map them to its
  backend without a UUID dependency (CHANGELOG 0.3 and 0.4).
- **Trade-off:** the text of a vector document is only available through metadata;
  text and hybrid queries depend on `_text` (or a bridge-specific field) being filled.
- **Trade-off:** `Metadata` is a mutable `\ArrayObject`, so the immutability of the
  document classes does not extend to their metadata.

## Alternatives Considered

- **A single document class with an optional vector**: not chosen because every store
  and transformer would have to check which state it was handed.
- **Typing stores against `VectorDocument`**: used until 0.14 and replaced by the
  interface to let stores return their own document objects.

## References

- `src/Document/DocumentInterface.php`, `src/Document/TextDocument.php`.
- `src/Document/VectorDocumentInterface.php`, `src/Document/VectorDocument.php`.
- `src/Document/Metadata.php`: reserved keys.
- `src/Document/Vectorizer.php`: the only bridge between the two states.
- `CHANGELOG.md`: 0.3, 0.4, 0.14 and 0.16 entries.

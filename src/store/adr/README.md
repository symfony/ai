# Architecture Decision Records

Architecture Decision Records of the Store component; the process and template live in
the repository's root `adr/README.md`.

| ADR | Decision |
|-----|----------|
| [0001](0001-split-store-contract-from-store-lifecycle.md) | **Split the store contract from the store lifecycle.** `StoreInterface` covers data operations, while `setup()` and `drop()` live on the separate, opt-in `ManagedStoreInterface`. |
| [0002](0002-separate-input-documents-from-vector-documents.md) | **Separate input documents from vector documents.** `DocumentInterface` is what gets indexed, `VectorDocumentInterface` is what stores persist and return, and only a vectorizer converts between them. |
| [0003](0003-indexing-is-a-load-filter-transform-vectorize-store-pipeline.md) | **Indexing is a load, filter, transform, vectorize, store pipeline.** Small interfaces over iterables are composed by `DocumentProcessor`, which batches vectorization and store writes in chunks. |
| [0004](0004-queries-are-typed-objects-negotiated-through-supports.md) | **Queries are typed objects negotiated through supports().** Vector, text and hybrid searches are separate query classes that a store declares support for and the `Retriever` picks from. |
| [0005](0005-backends-plug-in-as-bridge-packages-under-src-bridge.md) | **Backends plug in as bridge packages under src/Bridge.** Each backend is a separately split package that implements the core interfaces and reuses the core exceptions and integration test case. |

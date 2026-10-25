---
status: accepted
date: 2026-10-25
---

# 0003. Indexing is a load, filter, transform, vectorize, store pipeline

## Context and Problem Statement

Getting content into a vector store always takes the same steps: read it from a
source, drop what is not wanted, split or clean it, compute embeddings and save them.
Each step varies on its own (a Markdown file or an RSS feed, a text splitter or an LLM
summary). Embedding calls are expensive, so the steps must be composable and the
platform calls batched.

## Decision

Each step is a small interface in `src/Document/` that works on `iterable`s of
`DocumentInterface`:

- `LoaderInterface::load(?string $source, array $options)`: for example
  `TextFileLoader`, `MarkdownLoader`, `CsvLoader`, `RssFeedLoader`, `DirectoryLoader`.
- `FilterInterface::filter()`: for example `TextContainsFilter`.
- `TransformerInterface::transform()`: for example `TextSplitTransformer`,
  `SummaryGeneratorTransformer`, `ChunkDelayTransformer`, combined by
  `ChainTransformer`.
- `VectorizerInterface::vectorize()`: `Vectorizer` wraps a Platform `PlatformInterface`
  and a model name and always sends one batched `invoke()` per call.

`Indexer\DocumentProcessor` runs the filters, then the transformers, then vectorizes and
adds to the `StoreInterface` in chunks of `chunk_size` documents (default 50). Filters
run before transformers so unwanted content is dropped before any work is spent on it.
`IndexerInterface::index()` is the entry point: `Indexer\SourceIndexer` reads sources
through a loader, `Indexer\DocumentIndexer` takes documents directly, and
`Indexer\ConfiguredSourceIndexer` sets default sources.

## Consequences

- **Good:** loaders, filters and transformers are reusable and easy to test, since they
  only map iterables of documents.
- **Good:** iterables keep the pipeline lazy until the processor collects a chunk, so a
  large source is never held in memory as a whole.
- **Good:** chunking limits the size of each platform request and each store write.
- **Trade-off:** `DocumentProcessor` fixes the step order; a filter can run after a
  transformer only when wrapped in a transformer.
- **Trade-off:** `SourceIndexer` passes the same `$options` to the loader and the
  processor, so their option names share one namespace.

## Alternatives Considered

- **One `Indexer` class for every input**: existed until #1526 split it into
  `SourceIndexer` and `DocumentIndexer` over a shared `DocumentProcessor`, so documents
  can be indexed without a loader.
- **Choosing per model between one batched call and one call per document**:
  `Vectorizer` checked the model catalog for `Capability::INPUT_MULTIPLE`. Dropped in
  #2230, because every embedding bridge already accepts a list, and providers without
  the capability were silently embedded one call per document.

## History

- 2024-08-25, 0a65c502 by Christopher Hertel: added `DocumentEmbedder`, which embedded documents in chunks and stored them.
- 2025-07-04, #57 by @chr-hertel (Christopher Hertel): added `LoaderInterface`, `TransformerInterface`, `Vectorizer` and the `Indexer` (from php-llm/llm-chain#343).
- 2025-09-17, #621 by @OskarStark (Oskar Stark): added `FilterInterface`, run after loading and before transformers.
- 2026-01-30, #1526 by @chr-hertel (Christopher Hertel): split `Indexer` into `SourceIndexer` and `DocumentIndexer` over the shared `DocumentProcessor`.
- 2026-06-24, #2230 by @chr-hertel (Christopher Hertel): `Vectorizer` always batches embeddings instead of reading the model catalog.

## References

- `src/IndexerInterface.php`: the pipeline entry point.
- `src/Indexer/DocumentProcessor.php`: step order and chunking.
- `src/Indexer/SourceIndexer.php`, `src/Indexer/DocumentIndexer.php`,
  `src/Indexer/ConfiguredSourceIndexer.php`.
- `src/Document/LoaderInterface.php`, `src/Document/FilterInterface.php`,
  `src/Document/TransformerInterface.php`, `src/Document/VectorizerInterface.php`.
- `src/Document/Loader/`, `src/Document/Filter/`, `src/Document/Transformer/`.

---
status: accepted
date: 2026-10-25
---

# 0003. Indexing is a load, filter, transform, vectorize, store pipeline

## Context and Problem Statement

Getting content into a vector store always involves the same steps: read it from a
source, drop what is not wanted, split or clean it, compute embeddings and write them.
Each step varies independently (a Markdown file versus an RSS feed, a text splitter
versus an LLM summary), and embedding calls are expensive, so the steps must be
composable and the platform calls batched.

## Decision

Each step is a small interface in `src/Document/` working on `iterable`s of
`DocumentInterface`:

- `LoaderInterface::load(?string $source, array $options)`: for example
  `TextFileLoader`, `MarkdownLoader`, `CsvLoader`, `RssFeedLoader`, `DirectoryLoader`.
- `FilterInterface::filter()`: for example `TextContainsFilter`.
- `TransformerInterface::transform()`: for example `TextSplitTransformer`,
  `SummaryGeneratorTransformer`, `ChunkDelayTransformer`, combined by
  `ChainTransformer`.
- `VectorizerInterface::vectorize()`: `Vectorizer` wraps a Platform
  `PlatformInterface` and a model name and sends one batched `invoke()` per call.

`Indexer\DocumentProcessor` runs filters, then transformers, then vectorizes and adds to
the `StoreInterface` in chunks of `chunk_size` documents (default 50).
`IndexerInterface::index()` is the entry point: `Indexer\SourceIndexer` feeds sources
through a loader, `Indexer\DocumentIndexer` accepts documents directly, and
`Indexer\ConfiguredSourceIndexer` pre-configures default sources.

## Consequences

- **Good:** loaders, filters and transformers are reusable in isolation and easy to
  test, since they only map iterables of documents.
- **Good:** iterables keep the pipeline lazy until the processor collects a chunk, so
  large sources are not held in memory as a whole.
- **Good:** chunking bounds both the size of a platform request and of a store write.
- **Trade-off:** the step order is fixed by `DocumentProcessor`; a filter cannot run
  after a transformer except by wrapping it in a transformer.
- **Trade-off:** `SourceIndexer` passes the same `$options` to the loader and the
  processor (CHANGELOG 0.14), so option names share one namespace.

## Alternatives Considered

- **A single monolithic `Indexer` class**: existed until 0.4 and was removed in favor of
  `SourceIndexer` and `DocumentIndexer` over a shared `DocumentProcessor`.
- **Vectorizing one document per platform call**: dropped in 0.11; `Vectorizer` now
  always batches.

## References

- `src/IndexerInterface.php`: the pipeline entry point.
- `src/Indexer/DocumentProcessor.php`: step order and chunking.
- `src/Indexer/SourceIndexer.php`, `src/Indexer/DocumentIndexer.php`,
  `src/Indexer/ConfiguredSourceIndexer.php`.
- `src/Document/LoaderInterface.php`, `src/Document/FilterInterface.php`,
  `src/Document/TransformerInterface.php`, `src/Document/VectorizerInterface.php`.
- `src/Document/Loader/`, `src/Document/Filter/`, `src/Document/Transformer/`.

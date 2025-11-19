# AGENTS.md

AI agent guidance for the Store component.

## Component Overview

Low-level abstraction for vector stores enabling RAG applications. Unified interfaces for various vector database implementations.

## Architecture

### Core Interfaces
- **StoreInterface**: Main interface with `add()` and `query()` methods
- **ManagedStoreInterface**: Extends with `setup()` and `drop()` lifecycle methods
- **Indexer**: High-level service converting TextDocuments to VectorDocuments

### Bridge Pattern
Multiple vector store implementations:

**Database**: Postgres, MariaDB, ClickHouse, MongoDB, Neo4j, SurrealDB
**Cloud**: Azure AI Search, Pinecone
**Search**: Meilisearch, Typesense, Weaviate, Qdrant, Milvus
**Local**: InMemoryStore, CacheStore (PSR-6)
**External**: ChromaDb (requires codewithkyrian/chromadb-php)

### Document System
- **TextDocument**: Input documents with text and metadata
- **VectorDocument**: Documents with embedded vectors for storage
- **Vectorizer**: Converts TextDocuments using AI Platform
- **Transformers**: ChainTransformer, TextSplitTransformer, ChunkDelayTransformer

## Essential Commands

### Testing
```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/Bridge/Local/InMemoryStoreTest.php
vendor/bin/phpunit --filter testMethodName
```

### Code Quality
```bash
vendor/bin/phpstan analyse
```

### Dependencies
```bash
composer install
```

## Key Dependencies

- **symfony/ai-platform**: AI model integration and vectorization
- **psr/log**: Logging throughout indexing process
- **symfony/http-client**: HTTP-based vector store communication

## Development Notes

- Bridge pattern architecture with corresponding test structure
- PHPUnit 11+ with strict configuration
- Document preprocessing with transformers
- Batch indexing for performance
- Unified interface across all vector store types
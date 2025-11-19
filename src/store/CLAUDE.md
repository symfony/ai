# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the Store component of the Symfony AI ecosystem, providing a low-level abstraction for storing and retrieving documents in vector stores. The component enables Retrieval Augmented Generation (RAG) applications by offering unified interfaces for various vector database implementations.

## Development Commands

### Testing
Run the full test suite:
```bash
vendor/bin/phpunit
```

Run tests for a specific bridge (e.g., InMemory):
```bash
vendor/bin/phpunit tests/Bridge/Local/InMemoryStoreTest.php
```

Run a single test method:
```bash
vendor/bin/phpunit --filter testMethodName
```

### Code Quality
Run PHPStan static analysis:
```bash
vendor/bin/phpstan analyse
```

### Installation
Install dependencies:
```bash
composer install
```

## Architecture

### Core Interfaces
- **StoreInterface**: Main interface defining `add()` and `query()` methods for vector document storage and retrieval
- **ManagedStoreInterface**: Extension interface providing `setup()` and `drop()` methods for store lifecycle management
- **Indexer**: High-level service that converts TextDocuments to VectorDocuments and stores them in batches

### Bridge Pattern Architecture
The component follows a bridge pattern with implementations for multiple vector stores:

**Database Bridges**: Postgres, MariaDB, ClickHouse, MongoDB, Neo4j, SurrealDB
**Cloud Service Bridges**: Azure AI Search, Pinecone
**Search Engine Bridges**: Meilisearch, Typesense, Weaviate, Qdrant, Milvus
**Local Bridges**: InMemoryStore, CacheStore (PSR-6)
**External Service Bridges**: ChromaDb (requires codewithkyrian/chromadb-php)

### Document System
- **TextDocument**: Input documents containing text and metadata
- **VectorDocument**: Documents with embedded vectors for storage
- **Vectorizer**: Converts TextDocuments to VectorDocuments using AI Platform
- **Transformers**: ChainTransformer, TextSplitTransformer, ChunkDelayTransformer for document preprocessing

### Key Dependencies
- **symfony/ai-platform**: For AI model integration and vectorization
- **psr/log**: For logging throughout the indexing process
- **symfony/http-client**: For HTTP-based vector store communication

### Test Architecture
Tests follow the same bridge structure as source code, with each store implementation having corresponding test classes. Tests use PHPUnit 11+ with strict configuration for coverage and error handling.
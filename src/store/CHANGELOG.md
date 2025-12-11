CHANGELOG
=========

0.1
---

 * Add core store interfaces:
   - `StoreInterface` for storing and querying document stores
   - `ManagedStoreInterface` for stores, which can be initialized or dropped
 * Add document types:
   - `TextDocument` for raw text with UUID, content, and metadata
   - `VectorDocument` for vectorized documents with embeddings and similarity scores
   - `Metadata` for key-value document metadata storage
 * Add document loading system:
   - `LoaderInterface` contract for various document sources
   - `TextFileLoader` for loading text files as TextDocuments
 * Add document transformation pipeline:
   - `TransformerInterface` contract for document processing
   - `TextSplitTransformer` for splitting large documents into chunks
   - `ChainTransformer` for combining multiple transformers
   - `ChunkDelayTransformer` for rate limiting during processing
 * Add vectorization support:
   - `Vectorizer` for converting TextDocuments to VectorDocuments
   - Batch vectorization support for compatible platforms
   - Single document vectorization with fallback
 * Add high-level `Indexer` service:
   - Orchestrates document processing pipeline
   - Accepts TextDocuments, vectorizes and stores in chunks
   - Configurable batch processing
 * Add `InMemoryStore` and `CacheStore` implementations with multiple distance algorithms:
   - Cosine similarity
   - Angular distance
   - Euclidean distance
   - Manhattan distance
   - Chebyshev distance
 * Add store bridge implementations:
   - Azure AI Search
   - ChromaDB
   - ClickHouse
   - Cloudflare
   - Manticore Search
   - MariaDB
   - Meilisearch
   - MongoDB
   - Neo4j
   - OpenSearch
   - Pinecone
   - PostgreSQL with pgvector extension
   - Qdrant
   - Redis
   - Supabase
   - SurrealDB
   - Typesense
   - Weaviate
 * Add Retrieval Augmented Generation (RAG) support:
   - Document embedding storage
   - Similarity search for relevant documents
   - Dynamic context extension for AI applications
 * Add query features:
   - Vector similarity search with configurable options
   - Minimum score filtering
   - Result limiting
   - Distance/similarity scoring
 * Add Meilisearch hybrid search support with a configurable `semanticRatio` parameter to control the balance between semantic (vector) and full-text search.
 * Add custom exception hierarchy with `ExceptionInterface`
 * Add support for specific exceptions for invalid arguments and runtime errors

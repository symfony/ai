CHANGELOG
=========

0.1
---

 * Add core store interfaces:
   - `StoreInterface` for basic document storage
   - `VectorStoreInterface` extending with similarity search capabilities
   - `InitializableStoreInterface` for stores requiring initialization
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
 * Add `InMemoryStore` implementation with multiple distance algorithms:
   - Cosine similarity
   - Angular distance
   - Euclidean distance
   - Manhattan distance
   - Chebyshev distance
 * Add store bridge implementations:
   - PostgreSQL with pgvector extension
   - MariaDB
   - MongoDB
   - Azure AI Search
   - Meilisearch
   - ChromaDB
   - Pinecone
   - Qdrant
   - SurrealDB
   - Neo4j
 * Add Retrieval Augmented Generation (RAG) support:
   - Document embedding storage
   - Similarity search for relevant documents
   - Dynamic context extension for AI applications
 * Add query features:
   - Vector similarity search with configurable options
   - Minimum score filtering
   - Result limiting
   - Distance/similarity scoring
 * Add custom exception hierarchy with `ExceptionInterface`
 * Add support for specific exceptions for invalid arguments and runtime errors

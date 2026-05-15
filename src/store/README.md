# Symfony AI - Store Component

The Store component provides a low-level abstraction for storing and retrieving documents in a vector store.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

## Installation

```bash
composer require symfony/ai-store
```

## Store Bridges

To use a specific vector store, install the corresponding bridge package:

| Store                | Package                             |
|----------------------|-------------------------------------|
| AWS S3 Vectors       | `symfony/ai-s3vectors-store`        |
| Azure AI Search      | `symfony/ai-azure-search-store`     |
| Cache                | `symfony/ai-cache-store`            |
| ChromaDB             | `symfony/ai-chroma-db-store`        |
| ClickHouse           | `symfony/ai-click-house-store`      |
| Cloudflare Vectorize | `symfony/ai-cloudflare-store`       |
| Elasticsearch        | `symfony/ai-elasticsearch-store`    |
| ManticoreSearch      | `symfony/ai-manticore-search-store` |
| MariaDB              | `symfony/ai-maria-db-store`         |
| Meilisearch          | `symfony/ai-meilisearch-store`      |
| Milvus               | `symfony/ai-milvus-store`           |
| MongoDB Atlas        | `symfony/ai-mongo-db-store`         |
| Neo4j                | `symfony/ai-neo4j-store`            |
| OpenSearch           | `symfony/ai-open-search-store`      |
| Pinecone             | `symfony/ai-pinecone-store`         |
| PostgreSQL pgvector  | `symfony/ai-postgres-store`         |
| Qdrant               | `symfony/ai-qdrant-store`           |
| Redis                | `symfony/ai-redis-store`            |
| SQLite               | `symfony/ai-sqlite-store`           |
| Supabase             | `symfony/ai-supabase-store`         |
| SurrealDB            | `symfony/ai-surreal-db-store`       |
| Typesense            | `symfony/ai-typesense-store`        |
| Vektor               | `symfony/ai-vektor-store`           |
| Weaviate             | `symfony/ai-weaviate-store`         |

**This repository is a READ-ONLY sub-tree split**. See
https://github.com/symfony/ai to create issues or submit pull requests.

## Resources

- [Documentation](https://symfony.com/doc/current/ai/components/store.html)
- [Report issues](https://github.com/symfony/ai/issues) and
  [send Pull Requests](https://github.com/symfony/ai/pulls)
  in the [main Symfony AI repository](https://github.com/symfony/ai)

Store Bridges
=============

Each store bridge is a separate Composer package. The following bridges are available.

.. note::

    ``InMemory`` and ``Symfony Cache`` stores load all data into PHP memory during queries
    and can only be used when the dataset fits within PHP's memory limit.
    They are intended for development and testing.

Local Stores
------------

InMemory
~~~~~~~~

Stores vectors in a PHP array. Data is not persisted and is lost when the PHP process ends.

.. code-block:: php

    use Symfony\AI\Store\Bridge\InMemory\Store;

    $store = new Store();

Symfony Cache
~~~~~~~~~~~~~

Stores vectors using a PSR-6 cache adapter. Persistence depends on the adapter used.

.. code-block:: terminal

    $ composer require symfony/cache

.. code-block:: php

    use Symfony\AI\Store\Bridge\Cache\Store;
    use Symfony\Component\Cache\Adapter\FilesystemAdapter;

    $store = new Store(new FilesystemAdapter());

Both stores support configurable distance strategies and metadata filtering.
See :doc:`/components/store` for details.

SQL Databases
-------------

Postgres
~~~~~~~~

Vector storage using `pgvector`_, the PostgreSQL extension for vector similarity search.

.. code-block:: terminal

    $ composer require symfony/ai-postgres-store

**Requirements:** PostgreSQL 14+, ``pgvector`` extension, PHP ``ext-pdo``

Database setup:

.. code-block:: sql

    CREATE EXTENSION IF NOT EXISTS vector;

    CREATE TABLE IF NOT EXISTS documents (
        id UUID PRIMARY KEY,
        embedding vector(1536) NOT NULL,
        metadata JSONB
    );

    CREATE INDEX ON documents USING hnsw (embedding vector_cosine_ops);

.. code-block:: php

    use Symfony\AI\Store\Bridge\Postgres\Distance;
    use Symfony\AI\Store\Bridge\Postgres\Store;

    $pdo = new \PDO('pgsql:host=localhost;dbname=mydb', $user, $password);
    $store = Store::fromPdo($pdo, tableName: 'documents', vectorFieldName: 'embedding', distance: Distance::Cosine);

    // Or from a Doctrine DBAL connection
    $store = Store::fromDbal($connection, tableName: 'documents', vectorFieldName: 'embedding', distance: Distance::Cosine);

**Available distance metrics:** ``Distance::Cosine``, ``Distance::L2``, ``Distance::InnerProduct``, ``Distance::L1``

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            postgres:
                my_store:
                    dsn: '%env(DATABASE_URL)%'
                    table: 'documents'
                    vector_field: 'embedding'
                    distance: 'cosine'

The Postgres store also supports hybrid vector + full-text search via ``HybridQuery``.

MariaDB
~~~~~~~

Vector storage using MariaDB's native ``VECTOR`` column type (MariaDB 11.7+).

.. code-block:: terminal

    $ composer require symfony/ai-maria-db-store

**Requirements:** MariaDB 11.7+, PHP ``ext-pdo``

Database setup:

.. code-block:: sql

    CREATE TABLE IF NOT EXISTS documents (
        id CHAR(36) PRIMARY KEY,
        embedding VECTOR(1536) NOT NULL,
        metadata JSON
    );

    CREATE VECTOR INDEX ON documents (embedding);

.. code-block:: php

    use Symfony\AI\Store\Bridge\MariaDb\Store;

    $store = Store::fromPdo($pdo, tableName: 'documents', indexName: 'embedding_idx', vectorFieldName: 'embedding');

    // Or from a Doctrine DBAL connection
    $store = Store::fromDbal($connection, tableName: 'documents', indexName: 'embedding_idx', vectorFieldName: 'embedding');

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            mariadb:
                my_store:
                    dsn: '%env(DATABASE_URL)%'
                    table: 'documents'
                    index: 'embedding_idx'
                    vector_field: 'embedding'

Supabase
~~~~~~~~

Vector storage using `Supabase`_ with the ``pgvector`` extension through the REST API.

.. code-block:: terminal

    $ composer require symfony/ai-supabase-store

.. note::

    Unlike the Postgres store, Supabase requires manual schema setup because it doesn't
    allow arbitrary SQL execution via REST API.

Database setup (run in the Supabase SQL Editor):

.. code-block:: sql

    CREATE EXTENSION IF NOT EXISTS vector;

    CREATE TABLE IF NOT EXISTS documents (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        embedding vector(768) NOT NULL,
        metadata JSONB
    );

    CREATE OR REPLACE FUNCTION match_documents(
        query_embedding vector(768),
        match_count int DEFAULT 10,
        match_threshold float DEFAULT 0.0
    )
    RETURNS TABLE (id UUID, embedding vector, metadata JSONB, score float)
    LANGUAGE sql
    AS $$
        SELECT documents.id, documents.embedding, documents.metadata,
               1 - (documents.embedding <=> query_embedding) AS score
        FROM documents
        WHERE 1 - (documents.embedding <=> query_embedding) >= match_threshold
        ORDER BY documents.embedding <=> query_embedding ASC
        LIMIT match_count;
    $$;

    CREATE INDEX IF NOT EXISTS documents_embedding_idx
    ON documents USING ivfflat (embedding vector_cosine_ops);

.. code-block:: php

    use Symfony\AI\Store\Bridge\Supabase\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        'https://your-project.supabase.co',
        'your-anon-key',
        'documents',        // table name
        'embedding',        // vector field name
        768,                // vector dimension
        'match_documents',  // function name
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            supabase:
                my_store:
                    url: 'https://your-project.supabase.co'
                    api_key: '%env(SUPABASE_API_KEY)%'
                    table: 'documents'
                    vector_field: 'embedding'
                    vector_dimension: 768
                    function_name: 'match_documents'

Search Engines
--------------

Elasticsearch
~~~~~~~~~~~~~

Vector storage using `Elasticsearch`_'s ``dense_vector`` field type.

.. code-block:: terminal

    $ composer require symfony/ai-elasticsearch-store

**Requirements:** Elasticsearch 8.0+

.. code-block:: php

    use Symfony\AI\Store\Bridge\Elasticsearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpoint: 'https://localhost:9200',
        indexName: 'my_documents',
        vectorsField: 'embedding',
        dimensions: 1536,
        similarity: 'cosine',  // cosine, dot_product, l2_norm
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            elasticsearch:
                my_store:
                    endpoint: '%env(ELASTICSEARCH_URL)%'
                    index: 'my_documents'
                    vector_field: 'embedding'
                    dimensions: 1536
                    similarity: 'cosine'

The index is created automatically via ``ai:store:setup``. Docker: ``docker run -p 9200:9200 docker.elastic.co/elasticsearch/elasticsearch:8.17.0``

OpenSearch
~~~~~~~~~~

Vector storage using `OpenSearch`_'s k-NN plugin.

.. code-block:: terminal

    $ composer require symfony/ai-open-search-store

**Requirements:** OpenSearch 2.0+

.. code-block:: php

    use Symfony\AI\Store\Bridge\OpenSearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpoint: 'https://localhost:9200',
        indexName: 'my_documents',
        vectorsField: 'embedding',
        dimensions: 1536,
        spaceType: 'cosinesimil',  // cosinesimil, l2, innerproduct, hamming
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            opensearch:
                my_store:
                    endpoint: '%env(OPENSEARCH_URL)%'
                    index: 'my_documents'
                    vector_field: 'embedding'
                    dimensions: 1536
                    space_type: 'cosinesimil'

The index is created automatically via ``ai:store:setup``. Docker: ``docker run -p 9200:9200 opensearchproject/opensearch:latest``

ManticoreSearch
~~~~~~~~~~~~~~~

Vector storage using `ManticoreSearch`_ with HNSW-based similarity search.

.. code-block:: terminal

    $ composer require symfony/ai-manticore-search-store

**Requirements:** ManticoreSearch 6.2+

.. code-block:: php

    use Symfony\AI\Store\Bridge\ManticoreSearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        host: 'http://localhost:9308',
        table: 'documents',
        field: 'embedding',
        type: 'hnsw',
        similarity: 'cosine',  // cosine, l2
        dimensions: 1536,
        quantization: 'none',  // none, pq, sq
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            manticoresearch:
                my_store:
                    host: '%env(MANTICORESEARCH_URL)%'
                    table: 'documents'
                    field: 'embedding'
                    dimensions: 1536
                    similarity: 'cosine'

The table is created automatically via ``ai:store:setup``. Docker: ``docker run -p 9308:9308 manticoresearch/manticore:latest``

Meilisearch
~~~~~~~~~~~

Hybrid (vector + keyword) search using `Meilisearch`_ 1.6+.

.. code-block:: terminal

    $ composer require symfony/ai-meilisearch-store

.. code-block:: php

    use Symfony\AI\Store\Bridge\Meilisearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:7700',
        apiKey: 'masterKey',
        indexName: 'documents',
        embedder: 'default',
        vectorFieldName: '_vectors',
        embeddingsDimension: 1536,
        semanticRatio: 0.5,  // 0.0 = keyword only, 1.0 = semantic only
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            meilisearch:
                my_store:
                    endpoint: '%env(MEILISEARCH_URL)%'
                    api_key: '%env(MEILISEARCH_API_KEY)%'
                    index: 'documents'
                    embedder: 'default'
                    dimensions: 1536
                    semantic_ratio: 0.5

Enable the vector store feature before use:

.. code-block:: terminal

    $ curl -X PATCH 'http://localhost:7700/experimental-features/' \
      -H 'Authorization: Bearer masterKey' \
      -H 'Content-Type: application/json' \
      --data '{"vectorStore": true}'

Docker: ``docker run -p 7700:7700 getmeili/meilisearch:latest``

Typesense
~~~~~~~~~

Vector storage using `Typesense`_ 0.25+.

.. code-block:: terminal

    $ composer require symfony/ai-typesense-store

.. code-block:: php

    use Symfony\AI\Store\Bridge\Typesense\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:8108',
        apiKey: 'your-api-key',
        collection: 'documents',
        vectorFieldName: '_vectors',
        embeddingsDimension: 1536,
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            typesense:
                my_store:
                    endpoint: '%env(TYPESENSE_URL)%'
                    api_key: '%env(TYPESENSE_API_KEY)%'
                    collection: 'documents'
                    dimensions: 1536

The collection is created automatically via ``ai:store:setup``. Docker: ``docker run -p 8108:8108 typesense/typesense:latest``

Vector Databases
----------------

Qdrant
~~~~~~

Vector storage using `Qdrant`_, a high-performance vector database.

.. code-block:: terminal

    $ composer require symfony/ai-qdrant-store

.. code-block:: php

    use Symfony\AI\Store\Bridge\Qdrant\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:6333',
        apiKey: '',  // empty for local, required for Qdrant Cloud
        collectionName: 'my_documents',
        embeddingsDimension: 1536,
        embeddingsDistance: 'Cosine',  // Cosine, Euclid, Dot, Manhattan
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            qdrant:
                my_store:
                    endpoint: '%env(QDRANT_URL)%'
                    api_key: '%env(QDRANT_API_KEY)%'
                    collection: 'my_documents'
                    vector_dimension: 1536
                    distance: 'Cosine'

The collection is created automatically via ``ai:store:setup``. Docker: ``docker run -p 6333:6333 qdrant/qdrant``

Milvus
~~~~~~

Vector storage using `Milvus`_, a cloud-native vector database.

.. code-block:: terminal

    $ composer require symfony/ai-milvus-store

.. code-block:: php

    use Symfony\AI\Store\Bridge\Milvus\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:19530',
        apiKey: '',  // empty for local, required for Zilliz Cloud
        database: 'default',
        collection: 'my_documents',
        vectorFieldName: 'embedding',
        dimensions: 1536,
        metricType: 'COSINE',  // COSINE, L2, IP
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            milvus:
                my_store:
                    endpoint: '%env(MILVUS_URL)%'
                    api_key: '%env(MILVUS_API_KEY)%'
                    collection: 'my_documents'
                    dimensions: 1536
                    metric_type: 'COSINE'

The collection is created automatically via ``ai:store:setup``. Docker: ``docker run -p 19530:19530 milvusdb/milvus:latest standalone``

Weaviate
~~~~~~~~

Vector storage using `Weaviate`_ with GraphQL-based queries.

.. code-block:: terminal

    $ composer require symfony/ai-weaviate-store

.. note::

    Weaviate collection names must start with an uppercase letter.

.. code-block:: php

    use Symfony\AI\Store\Bridge\Weaviate\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:8080',
        apiKey: '',  // empty for local, required for Weaviate Cloud
        collection: 'Document',
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            weaviate:
                my_store:
                    endpoint: '%env(WEAVIATE_URL)%'
                    api_key: '%env(WEAVIATE_API_KEY)%'
                    collection: 'Document'

The collection is created automatically via ``ai:store:setup``. Docker: ``docker run -p 8080:8080 cr.weaviate.io/semitechnologies/weaviate:latest``

ChromaDB
~~~~~~~~

Vector storage using `ChromaDB`_, an open-source AI-native vector database.

.. code-block:: terminal

    $ composer require symfony/ai-chroma-db-store codewithkyrian/chromadb-php

**Additional dependency:** ``codewithkyrian/chromadb-php``

.. code-block:: php

    use Codewithkyrian\ChromaDB\ChromaDB;
    use Symfony\AI\Store\Bridge\ChromaDb\Store;

    $client = ChromaDB::client(host: 'localhost', port: 8000);
    $store = new Store($client, collectionName: 'my_documents');

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            chromadb:
                my_store:
                    collection: 'my_documents'
                    host: 'localhost'
                    port: 8000

The collection is created automatically the first time documents are added.
Docker: ``docker run -p 8000:8000 chromadb/chroma``

Pinecone
~~~~~~~~

Vector storage using `Pinecone`_, a managed vector database service.

.. code-block:: terminal

    $ composer require symfony/ai-pinecone-store probots-io/pinecone-php

**Additional dependency:** ``probots-io/pinecone-php``

**Required:** Pinecone API key

.. code-block:: php

    use Probots\Pinecone\Client as PineconeClient;
    use Symfony\AI\Store\Bridge\Pinecone\Store;

    $store = new Store(
        new PineconeClient($apiKey),
        indexName: 'my-index',
        namespace: 'default',
        filter: [],
        topK: 10,
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            pinecone:
                my_store:
                    api_key: '%env(PINECONE_API_KEY)%'
                    index: 'my-index'
                    namespace: 'default'

Create the Pinecone index before use (dimensions must match your embedding model):

.. code-block:: php

    $client->index()->createServerless(
        name: 'my-index',
        dimension: 1536,
        metric: 'cosine',
        cloud: 'aws',
        region: 'us-east-1',
    );

MongoDB Atlas
~~~~~~~~~~~~~

Vector storage using `MongoDB Atlas Vector Search`_.

.. code-block:: terminal

    $ composer require symfony/ai-mongo-db-store mongodb/mongodb

**Additional dependency:** ``mongodb/mongodb``

Before using the store, create a vector search index in Atlas UI under **Search → Create Search Index → JSON Editor**:

.. code-block:: json

    {
        "fields": [
            {
                "type": "vector",
                "path": "embedding",
                "numDimensions": 1536,
                "similarity": "cosine"
            }
        ]
    }

.. code-block:: php

    use MongoDB\Client;
    use Symfony\AI\Store\Bridge\MongoDb\Store;

    $store = new Store(
        new Client('mongodb+srv://user:password@cluster.mongodb.net'),
        databaseName: 'my_database',
        collectionName: 'documents',
        indexName: 'vector_index',
        vectorFieldName: 'embedding',
        embeddingsDimension: 1536,
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            mongodb:
                my_store:
                    uri: '%env(MONGODB_URI)%'
                    database: 'my_database'
                    collection: 'documents'
                    index_name: 'vector_index'
                    vector_field: 'embedding'
                    vector_dimension: 1536

.. note::

    The ``remove()`` operation is not supported by this bridge.

Cloud Services
--------------

Azure AI Search
~~~~~~~~~~~~~~~

Vector storage using `Azure AI Search`_.

.. code-block:: terminal

    $ composer require symfony/ai-azure-search-store

.. note::

    The index must be pre-created in the Azure Portal. This bridge does not create indexes automatically.

.. code-block:: php

    use Symfony\AI\Store\Bridge\AzureSearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'https://my-search.search.windows.net',
        apiKey: 'your-admin-api-key',
        indexName: 'my-index',
        apiVersion: '2023-11-01',
        vectorFieldName: 'embedding',
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            azure_search:
                my_store:
                    endpoint: '%env(AZURE_SEARCH_ENDPOINT)%'
                    api_key: '%env(AZURE_SEARCH_API_KEY)%'
                    index: 'my-index'
                    api_version: '2023-11-01'
                    vector_field: 'embedding'

Cloudflare Vectorize
~~~~~~~~~~~~~~~~~~~~

Vector storage using `Cloudflare Vectorize`_.

.. code-block:: terminal

    $ composer require symfony/ai-cloudflare-store

**Required:** Cloudflare account ID and API token

.. code-block:: php

    use Symfony\AI\Store\Bridge\Cloudflare\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        accountId: 'your-account-id',
        apiKey: 'your-api-token',
        index: 'my-index',
        dimensions: 1536,
        metric: 'cosine',  // cosine, euclidean, dotproduct
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            cloudflare:
                my_store:
                    account_id: '%env(CLOUDFLARE_ACCOUNT_ID)%'
                    api_key: '%env(CLOUDFLARE_API_KEY)%'
                    index: 'my-index'
                    dimensions: 1536
                    metric: 'cosine'

The index is created automatically via ``ai:store:setup``.

Other
-----

ClickHouse
~~~~~~~~~~

Vector storage using `ClickHouse`_'s cosine distance function.

.. code-block:: terminal

    $ composer require symfony/ai-click-house-store

**Requirements:** ClickHouse 23.5+

.. code-block:: php

    use Symfony\AI\Store\Bridge\ClickHouse\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        databaseName: 'default',
        tableName: 'documents',
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            clickhouse:
                my_store:
                    endpoint: '%env(CLICKHOUSE_URL)%'
                    database: 'default'
                    table: 'documents'

Manual table setup:

.. code-block:: sql

    CREATE TABLE IF NOT EXISTS documents (
        id UUID,
        embedding Array(Float32),
        metadata String
    ) ENGINE = MergeTree() ORDER BY id;

Or use ``ai:store:setup`` to create the table automatically. Docker: ``docker run -p 8123:8123 clickhouse/clickhouse-server:latest``

Neo4j
~~~~~

Vector storage using `Neo4j`_'s vector index (Neo4j 5.11+).

.. code-block:: terminal

    $ composer require symfony/ai-neo4j-store

.. code-block:: php

    use Symfony\AI\Store\Bridge\Neo4j\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:7474',
        username: 'neo4j',
        password: 'password',
        databaseName: 'neo4j',
        vectorIndexName: 'document_embeddings',
        nodeName: 'Document',
        embeddingsField: 'embedding',
        embeddingsDimension: 1536,
        embeddingsDistance: 'cosine',  // cosine, euclidean
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            neo4j:
                my_store:
                    endpoint: '%env(NEO4J_URL)%'
                    username: '%env(NEO4J_USERNAME)%'
                    password: '%env(NEO4J_PASSWORD)%'
                    index: 'document_embeddings'
                    node: 'Document'
                    vector_field: 'embedding'
                    dimensions: 1536
                    distance: 'cosine'

The vector index is created automatically via ``ai:store:setup``. Docker: ``docker run -p 7474:7474 -p 7687:7687 -e NEO4J_AUTH=neo4j/password neo4j:latest``

Redis Stack
~~~~~~~~~~~

Vector storage using `Redis Stack`_ with the RediSearch module.

.. code-block:: terminal

    $ composer require symfony/ai-redis-store

**Requirements:** Redis Stack, PHP ``ext-redis``

.. code-block:: php

    use Symfony\AI\Store\Bridge\Redis\Distance;
    use Symfony\AI\Store\Bridge\Redis\Store;

    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);

    $store = new Store(
        $redis,
        indexName: 'my_index',
        keyPrefix: 'vector:',
        distance: Distance::Cosine,  // Distance::Cosine, Distance::L2, Distance::IP
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            redis:
                my_store:
                    host: '127.0.0.1'
                    port: 6379
                    index: 'my_index'
                    prefix: 'vector:'
                    distance: 'cosine'

The index is created automatically via ``ai:store:setup``. Docker: ``docker run -p 6379:6379 redis/redis-stack-server:latest``

SurrealDB
~~~~~~~~~

Vector storage using `SurrealDB`_'s MTREE vector index (SurrealDB 1.5+).

.. code-block:: terminal

    $ composer require symfony/ai-surreal-db-store

.. code-block:: php

    use Symfony\AI\Store\Bridge\SurrealDb\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:8000',
        user: 'root',
        password: 'root',
        namespace: 'my_namespace',
        database: 'my_database',
        table: 'vectors',
        vectorFieldName: '_vectors',
        strategy: 'cosine',  // cosine, euclidean, manhattan, minkowski
        embeddingsDimension: 1536,
    );

Bundle configuration:

.. code-block:: yaml

    ai:
        store:
            surrealdb:
                my_store:
                    endpoint: '%env(SURREALDB_URL)%'
                    user: '%env(SURREALDB_USER)%'
                    password: '%env(SURREALDB_PASSWORD)%'
                    namespace: 'my_namespace'
                    database: 'my_database'
                    dimensions: 1536
                    strategy: 'cosine'

The index is created automatically via ``ai:store:setup``. Docker: ``docker run -p 8000:8000 surrealdb/surrealdb:latest start --user root --pass root``

.. _`pgvector`: https://github.com/pgvector/pgvector
.. _`Supabase`: https://supabase.com/
.. _`Elasticsearch`: https://www.elastic.co/elasticsearch
.. _`OpenSearch`: https://opensearch.org/
.. _`ManticoreSearch`: https://manticoresearch.com/
.. _`Meilisearch`: https://www.meilisearch.com/
.. _`Typesense`: https://typesense.org/
.. _`Qdrant`: https://qdrant.tech/
.. _`Milvus`: https://milvus.io/
.. _`Weaviate`: https://weaviate.io/
.. _`ChromaDB`: https://www.trychroma.com/
.. _`Pinecone`: https://www.pinecone.io/
.. _`MongoDB Atlas Vector Search`: https://www.mongodb.com/products/platform/atlas-vector-search
.. _`Azure AI Search`: https://azure.microsoft.com/products/ai-services/ai-search
.. _`Cloudflare Vectorize`: https://developers.cloudflare.com/vectorize/
.. _`ClickHouse`: https://clickhouse.com/
.. _`Neo4j`: https://neo4j.com/
.. _`Redis Stack`: https://redis.io/docs/stack/
.. _`SurrealDB`: https://surrealdb.com/

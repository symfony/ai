Store Bridges
=============

Every store bridge is a separate Composer package that implements
:class:`Symfony\\AI\\Store\\StoreInterface`, and almost all of them additionally implement
:class:`Symfony\\AI\\Store\\ManagedStoreInterface` to create and drop their infrastructure.

This page lists all available bridges with their package name, a minimal standalone setup and the
matching ``AiBundle`` configuration. Bridges with additional setup requirements have a dedicated
page linked from their section.

.. note::

    The ``InMemory`` and ``Symfony Cache`` stores load all data into the memory of the PHP process
    during queries, so they can only be used when the dataset fits into the PHP memory limit.
    They are meant for development and testing.

Local Stores
------------

InMemory
~~~~~~~~

Stores vectors in a PHP array. Data is not persisted and is lost when the PHP process ends. Ships
with the ``symfony/ai-store`` package, no additional dependency is required::

    use Symfony\AI\Store\InMemory\Store;

    $store = new Store();

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            memory:
                my_store:
                    strategy: 'cosine'

Symfony Cache
~~~~~~~~~~~~~

Stores vectors using a PSR-6 cache adapter. Persistence depends on the adapter that is used.

.. code-block:: terminal

    $ composer require symfony/ai-cache-store

::

    use Symfony\AI\Store\Bridge\Cache\Store;
    use Symfony\Component\Cache\Adapter\FilesystemAdapter;

    $store = new Store(new FilesystemAdapter());

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            cache:
                my_store:
                    service: 'cache.app'
                    cache_key: '_vectors'
                    strategy: 'cosine'

Both stores support configurable distance strategies, batched distance calculation and metadata
filtering - see :doc:`local` for the details.

Vektor
~~~~~~

File-based vector storage using `Vektor`_. The index is the storage directory itself.

.. code-block:: terminal

    $ composer require symfony/ai-vektor-store

::

    use Symfony\AI\Store\Bridge\Vektor\Store;

    $store = new Store('/path/to/var/share', dimensions: 1536);

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            vektor:
                my_store:
                    storage_path: '%kernel.project_dir%/var/share'
                    dimensions: 1536

.. note::

    Vektor supports neither removing documents in bulk nor listing them, so ``clear()`` recreates
    the storage directory.

SQL Databases
-------------

Postgres
~~~~~~~~

Vector storage using `pgvector`_, the PostgreSQL extension for vector similarity search.

.. code-block:: terminal

    $ composer require symfony/ai-postgres-store

**Requirements:** ``pgvector`` extension, PHP ``ext-pdo``

The table and index are created by ``setup()``::

    use Symfony\AI\Store\Bridge\Postgres\Distance;
    use Symfony\AI\Store\Bridge\Postgres\StoreFactory;

    $pdo = new \PDO('pgsql:host=localhost;dbname=mydb', $user, $password);
    $store = StoreFactory::createStoreFromPdo($pdo, 'documents', vectorFieldName: 'embedding', distance: Distance::Cosine);

    // or from a Doctrine DBAL connection
    $store = StoreFactory::createStoreFromDbal($connection, 'documents', vectorFieldName: 'embedding', distance: Distance::Cosine);

**Available distances:** ``Distance::Cosine``, ``Distance::InnerProduct``, ``Distance::L1``, ``Distance::L2`` (default)

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            postgres:
                my_store:
                    dsn: '%env(DATABASE_URL)%'
                    # or a Doctrine DBAL connection service instead of a dsn:
                    # dbal_connection: 'doctrine.dbal.default_connection'
                    table_name: 'documents'
                    vector_field: 'embedding'
                    distance: 'cosine'
                    setup_options:
                        vector_size: 1536
                        index_method: 'hnsw'
                        index_opclass: 'vector_cosine_ops'

This store also supports hybrid vector and full-text search via
:class:`Symfony\\AI\\Store\\Query\\HybridQuery`, which uses the configured ``lang`` for the
text search configuration.

MariaDB
~~~~~~~

Vector storage using MariaDB's native ``VECTOR`` column type.

.. code-block:: terminal

    $ composer require symfony/ai-maria-db-store

**Requirements:** MariaDB 11.7+, PHP ``ext-pdo``

::

    use Symfony\AI\Store\Bridge\MariaDb\Distance;
    use Symfony\AI\Store\Bridge\MariaDb\Store;

    $store = Store::fromPdo($pdo, 'documents', indexName: 'embedding_idx', vectorFieldName: 'embedding', distance: Distance::Cosine);

    // or from a Doctrine DBAL connection
    $store = Store::fromDbal($connection, 'documents', indexName: 'embedding_idx', vectorFieldName: 'embedding');

**Available distances:** ``Distance::Cosine``, ``Distance::Euclidean`` (default), ``Distance::Distance``

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            mariadb:
                my_store:
                    # name of a Doctrine DBAL connection
                    connection: 'default'
                    table_name: 'documents'
                    index_name: 'embedding_idx'
                    vector_field_name: 'embedding'
                    distance: 'cosine'
                    setup_options:
                        dimensions: 1536

SQLite
~~~~~~

Vector storage in a SQLite database, either with in-PHP distance calculation or with native vector
search through the `sqlite-vec`_ extension.

.. code-block:: terminal

    $ composer require symfony/ai-sqlite-store

**Requirements:** PHP ``ext-pdo_sqlite``

::

    use Symfony\AI\Store\Bridge\Sqlite\StoreFactory;

    $store = StoreFactory::create('sqlite:/path/to/store.db', 'documents');

    // or with the sqlite-vec extension for native vector search
    $store = StoreFactory::createVecStore('sqlite:/path/to/store.db', 'documents');

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            sqlite:
                my_store:
                    dsn: 'sqlite:%kernel.project_dir%/var/store.db'
                    # or a Doctrine DBAL connection service instead of a dsn:
                    # connection: 'doctrine.dbal.default_connection'
                    table_name: 'documents'
                    vec: false
                    distance: 'cosine'
                    vector_dimension: 1536

See :doc:`sqlite` for the differences between both stores and the ``sqlite-vec`` setup.

Supabase
~~~~~~~~

Vector storage using `Supabase`_ with the ``pgvector`` extension through the REST API.

.. code-block:: terminal

    $ composer require symfony/ai-supabase-store

.. note::

    Unlike the Postgres store, Supabase requires manual setup of the database schema, because it
    does not allow arbitrary SQL execution through the REST API.

::

    use Symfony\AI\Store\Bridge\Supabase\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        'https://your-project.supabase.co',
        'your-anon-key',
        table: 'documents',
        vectorFieldName: 'embedding',
        vectorDimension: 768,
        functionName: 'match_documents',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
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

See :doc:`supabase` for the SQL that creates the table and the ``match_documents`` function.

Search Engines
--------------

Elasticsearch
~~~~~~~~~~~~~

Vector storage using the ``dense_vector`` field type of `Elasticsearch`_.

.. code-block:: terminal

    $ composer require symfony/ai-elasticsearch-store

The index is created by ``setup()``::

    use Symfony\AI\Store\Bridge\Elasticsearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        'https://localhost:9200',
        'my_documents',
        vectorsField: '_vectors',
        dimensions: 1536,
        similarity: 'cosine',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            elasticsearch:
                my_store:
                    endpoint: '%env(ELASTICSEARCH_URL)%'
                    index_name: 'my_documents'
                    vectors_field: '_vectors'
                    dimensions: 1536
                    similarity: 'cosine'

OpenSearch
~~~~~~~~~~

Vector storage using the k-NN plugin of `OpenSearch`_.

.. code-block:: terminal

    $ composer require symfony/ai-open-search-store

The index is created by ``setup()``::

    use Symfony\AI\Store\Bridge\OpenSearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        'https://localhost:9200',
        'my_documents',
        vectorsField: '_vectors',
        dimensions: 1536,
        spaceType: 'cosinesimil',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            opensearch:
                my_store:
                    endpoint: '%env(OPENSEARCH_URL)%'
                    index_name: 'my_documents'
                    vectors_field: '_vectors'
                    dimensions: 1536
                    space_type: 'cosinesimil'

ManticoreSearch
~~~~~~~~~~~~~~~

Vector storage using `ManticoreSearch`_ with HNSW-based similarity search.

.. code-block:: terminal

    $ composer require symfony/ai-manticore-search-store

The table is created by ``setup()``::

    use Symfony\AI\Store\Bridge\ManticoreSearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        'http://localhost:9308',
        'documents',
        field: '_vectors',
        type: 'hnsw',
        similarity: 'cosine',
        dimensions: 1536,
        quantization: '8bit',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            manticoresearch:
                my_store:
                    endpoint: '%env(MANTICORESEARCH_URL)%'
                    table: 'documents'
                    field: '_vectors'
                    type: 'hnsw'
                    similarity: 'cosine'
                    dimensions: 1536
                    quantization: '8bit'

Meilisearch
~~~~~~~~~~~

Vector storage using the vector search of `Meilisearch`_.

.. code-block:: terminal

    $ composer require symfony/ai-meilisearch-store

The index and its embedder settings are created by ``setup()``::

    use Symfony\AI\Store\Bridge\Meilisearch\StoreFactory;

    $store = StoreFactory::create(
        'documents',
        'http://localhost:7700',
        'your-api-key',
        embedder: 'default',
        vectorFieldName: '_vectors',
        embeddingsDimension: 1536,
        semanticRatio: 1.0,
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            meilisearch:
                my_store:
                    endpoint: '%env(MEILISEARCH_URL)%'
                    api_key: '%env(MEILISEARCH_API_KEY)%'
                    index_name: 'documents'
                    embedder: 'default'
                    vector_field: '_vectors'
                    dimensions: 1536
                    semantic_ratio: 1.0

Typesense
~~~~~~~~~

Vector storage using the vector search of `Typesense`_.

.. code-block:: terminal

    $ composer require symfony/ai-typesense-store

The collection is created by ``setup()``::

    use Symfony\AI\Store\Bridge\Typesense\StoreFactory;

    $store = StoreFactory::create(
        'documents',
        'http://localhost:8108',
        'your-api-key',
        vectorFieldName: '_vectors',
        embeddingsDimension: 1536,
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            typesense:
                my_store:
                    endpoint: '%env(TYPESENSE_URL)%'
                    api_key: '%env(TYPESENSE_API_KEY)%'
                    collection: 'documents'
                    vector_field: '_vectors'
                    dimensions: 1536

Vector Databases
----------------

Qdrant
~~~~~~

Vector storage using `Qdrant`_.

.. code-block:: terminal

    $ composer require symfony/ai-qdrant-store

The collection is created by ``setup()``::

    use Symfony\AI\Store\Bridge\Qdrant\StoreFactory;

    $store = StoreFactory::create(
        'my_documents',
        'http://localhost:6333',
        'your-api-key',
        embeddingsDimension: 1536,
        embeddingsDistance: 'Cosine',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            qdrant:
                my_store:
                    endpoint: '%env(QDRANT_URL)%'
                    api_key: '%env(QDRANT_API_KEY)%'
                    collection_name: 'my_documents'
                    dimensions: 1536
                    distance: 'Cosine'
                    async: false

Milvus
~~~~~~

Vector storage using `Milvus`_.

.. code-block:: terminal

    $ composer require symfony/ai-milvus-store

The collection is created by ``setup()``::

    use Symfony\AI\Store\Bridge\Milvus\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        'http://localhost:19530',
        'your-api-key',
        'my_database',
        'my_documents',
        vectorFieldName: '_vectors',
        dimensions: 1536,
        metricType: 'COSINE',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            milvus:
                my_store:
                    endpoint: '%env(MILVUS_URL)%'
                    api_key: '%env(MILVUS_API_KEY)%'
                    database: 'my_database'
                    collection: 'my_documents'
                    vector_field: '_vectors'
                    dimensions: 1536
                    metric_type: 'COSINE'

.. tip::

    Pass ``['forceDatabaseCreation' => true]`` to ``setup()`` to create the database as well.

Weaviate
~~~~~~~~

Vector storage using `Weaviate`_.

.. code-block:: terminal

    $ composer require symfony/ai-weaviate-store

The collection is created by ``setup()``::

    use Symfony\AI\Store\Bridge\Weaviate\StoreFactory;

    $store = StoreFactory::create('Document', 'http://localhost:8080', 'your-api-key');

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            weaviate:
                my_store:
                    endpoint: '%env(WEAVIATE_URL)%'
                    api_key: '%env(WEAVIATE_API_KEY)%'
                    collection: 'Document'

ChromaDB
~~~~~~~~

Vector storage using `Chroma`_.

.. code-block:: terminal

    $ composer require symfony/ai-chroma-db-store codewithkyrian/chromadb-php

**Additional dependency:** ``codewithkyrian/chromadb-php``

The collection is created by ``setup()``::

    use Codewithkyrian\ChromaDB\Factory;
    use Symfony\AI\Store\Bridge\ChromaDb\Store;

    $client = (new Factory())->withHost('localhost')->withPort(8000)->connect();
    $store = new Store($client, 'my_documents');

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            chromadb:
                my_store:
                    # service id of a Codewithkyrian\ChromaDB\Client
                    client: 'Codewithkyrian\ChromaDB\Client'
                    collection: 'my_documents'
                    # optional service implementing Codewithkyrian\ChromaDB\Embeddings\EmbeddingFunction,
                    # required to query the store with a TextQuery, which ChromaDB embeds client-side
                    embedding_function: 'app.chromadb.embedding_function'

Pinecone
~~~~~~~~

Vector storage using `Pinecone`_.

.. code-block:: terminal

    $ composer require symfony/ai-pinecone-store probots-io/pinecone-php

**Additional dependency:** ``probots-io/pinecone-php``

::

    use Probots\Pinecone\Pinecone;
    use Symfony\AI\Store\Bridge\Pinecone\Store;

    $store = new Store(Pinecone::client('your-api-key', 'your-index-host'), 'my-index', namespace: 'default', topK: 3);

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            pinecone:
                my_store:
                    # service id of a Probots\Pinecone\Client
                    client: 'Probots\Pinecone\Client'
                    index_name: 'my-index'
                    namespace: 'default'
                    top_k: 3

MongoDB Atlas
~~~~~~~~~~~~~

Vector storage using `Atlas Vector Search`_.

.. code-block:: terminal

    $ composer require symfony/ai-mongo-db-store mongodb/mongodb

**Additional dependency:** ``mongodb/mongodb``, PHP ``ext-mongodb``

::

    use MongoDB\Client;
    use Symfony\AI\Store\Bridge\MongoDb\Store;

    $store = new Store(
        new Client('mongodb+srv://user:password@cluster.mongodb.net'),
        'my_database',
        'documents',
        'vector_index',
        vectorFieldName: 'vector',
        embeddingsDimension: 1536,
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            mongodb:
                my_store:
                    # service id of a MongoDB\Client
                    client: 'MongoDB\Client'
                    database: 'my_database'
                    collection: 'documents'
                    index_name: 'vector_index'
                    vector_field: 'vector'
                    bulk_write: false

.. note::

    This bridge requires a MongoDB Atlas cluster or the ``mongodb-atlas-local`` Docker image.
    Self-hosted MongoDB deployments do not support Atlas Vector Search.

See :doc:`mongodb` for the vector search index definition and its setup options.

Cloud Services
--------------

Azure AI Search
~~~~~~~~~~~~~~~

Vector storage using `Azure AI Search`_.

.. code-block:: terminal

    $ composer require symfony/ai-azure-search-store

::

    use Symfony\AI\Store\Bridge\AzureSearch\StoreFactory;

    $store = StoreFactory::create(
        'my-index',
        'vector',
        'https://my-search.search.windows.net',
        'your-admin-api-key',
        '2023-11-01',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            azuresearch:
                my_store:
                    endpoint: '%env(AZURE_SEARCH_ENDPOINT)%'
                    api_key: '%env(AZURE_SEARCH_API_KEY)%'
                    api_version: '2023-11-01'
                    index_name: 'my-index'
                    vector_field: 'vector'

.. note::

    This is the only store that does not implement
    :class:`Symfony\\AI\\Store\\ManagedStoreInterface`: the index has to be created upfront,
    ``ai:store:setup`` does not work for it.

Cloudflare Vectorize
~~~~~~~~~~~~~~~~~~~~

Vector storage using `Cloudflare Vectorize`_.

.. code-block:: terminal

    $ composer require symfony/ai-cloudflare-store

The index is created by ``setup()``::

    use Symfony\AI\Store\Bridge\Cloudflare\StoreFactory;

    $store = StoreFactory::create(
        'my-index',
        'your-account-id',
        'your-api-token',
        dimensions: 1536,
        metric: 'cosine',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            cloudflare:
                my_store:
                    account_id: '%env(CLOUDFLARE_ACCOUNT_ID)%'
                    api_key: '%env(CLOUDFLARE_API_KEY)%'
                    index_name: 'my-index'
                    dimensions: 1536
                    metric: 'cosine'

S3 Vectors
~~~~~~~~~~

Vector storage using `S3 Vectors`_.

.. code-block:: terminal

    $ composer require symfony/ai-s3vectors-store

::

    use AsyncAws\S3Vectors\S3VectorsClient;
    use Symfony\AI\Store\Bridge\S3Vectors\Store;

    $store = new Store(new S3VectorsClient(), 'my-bucket', 'my-index', topK: 3);

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            s3vectors:
                my_store:
                    vector_bucket_name: 'my-bucket'
                    index_name: 'my-index'
                    top_k: 3
                    configuration:
                        region: 'us-east-1'

See :doc:`s3-vectors` for the AWS credentials setup.

Other
-----

ClickHouse
~~~~~~~~~~

Vector storage using `ClickHouse`_.

.. code-block:: terminal

    $ composer require symfony/ai-click-house-store

The table is created by ``setup()``. The HTTP client has to be scoped to the ClickHouse DSN::

    use Symfony\AI\Store\Bridge\ClickHouse\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::createForBaseUri('http://default:password@localhost:8123'),
        databaseName: 'default',
        tableName: 'documents',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            clickhouse:
                my_store:
                    dsn: '%env(CLICKHOUSE_URL)%'
                    database: 'default'
                    table: 'documents'

Neo4j
~~~~~

Vector storage using the vector index of `Neo4j`_.

.. code-block:: terminal

    $ composer require symfony/ai-neo4j-store

The vector index is created by ``setup()``::

    use Symfony\AI\Store\Bridge\Neo4j\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        'http://localhost:7474',
        'neo4j',
        'your-password',
        'neo4j',
        'document_embeddings',
        'Document',
        embeddingsField: 'embeddings',
        embeddingsDimension: 1536,
        embeddingsDistance: 'cosine',
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            neo4j:
                my_store:
                    endpoint: '%env(NEO4J_URL)%'
                    username: '%env(NEO4J_USERNAME)%'
                    password: '%env(NEO4J_PASSWORD)%'
                    database: 'neo4j'
                    vector_index_name: 'document_embeddings'
                    node_name: 'Document'
                    vector_field: 'embeddings'
                    dimensions: 1536
                    distance: 'cosine'

Redis Stack
~~~~~~~~~~~

Vector storage using the vector similarity search of `Redis`_.

.. code-block:: terminal

    $ composer require symfony/ai-redis-store

**Requirements:** Redis Stack (or the RediSearch module), PHP ``ext-redis``

The index is created by ``setup()``::

    use Symfony\AI\Store\Bridge\Redis\Distance;
    use Symfony\AI\Store\Bridge\Redis\Store;

    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);

    $store = new Store($redis, 'my_index', keyPrefix: 'vector:', distance: Distance::Cosine);

**Available distances:** ``Distance::Cosine`` (default), ``Distance::L2``, ``Distance::Ip``

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            redis:
                my_store:
                    connection_parameters:
                        host: '127.0.0.1'
                        port: 6379
                    # or a \Redis service instead of connection_parameters:
                    # client: 'app.redis'
                    index_name: 'my_index'
                    key_prefix: 'vector:'
                    distance: 'COSINE'

SurrealDB
~~~~~~~~~

Vector storage using the vector index of `SurrealDB`_.

.. code-block:: terminal

    $ composer require symfony/ai-surreal-db-store

The table and index are created by ``setup()``::

    use Symfony\AI\Store\Bridge\SurrealDb\StoreFactory;

    $store = StoreFactory::create(
        'my_namespace',
        'my_database',
        'root',
        'root',
        'http://localhost:8000',
        table: 'vectors',
        vectorFieldName: '_vectors',
        strategy: 'cosine',
        embeddingsDimension: 1536,
    );

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            surrealdb:
                my_store:
                    endpoint: '%env(SURREALDB_URL)%'
                    username: '%env(SURREALDB_USER)%'
                    password: '%env(SURREALDB_PASSWORD)%'
                    namespace: 'my_namespace'
                    database: 'my_database'
                    table: 'vectors'
                    vector_field: '_vectors'
                    strategy: 'cosine'
                    dimensions: 1536

.. toctree::
    :maxdepth: 1
    :hidden:

    local
    sqlite
    supabase
    mongodb
    s3-vectors

.. _`Vektor`: https://github.com/centamiv/vektor
.. _`pgvector`: https://github.com/pgvector/pgvector
.. _`sqlite-vec`: https://github.com/asg017/sqlite-vec
.. _`Supabase`: https://supabase.com/
.. _`Elasticsearch`: https://www.elastic.co/elasticsearch
.. _`OpenSearch`: https://opensearch.org/
.. _`ManticoreSearch`: https://manticoresearch.com/
.. _`Meilisearch`: https://www.meilisearch.com/
.. _`Typesense`: https://typesense.org/
.. _`Qdrant`: https://qdrant.tech/
.. _`Milvus`: https://milvus.io/
.. _`Weaviate`: https://weaviate.io/
.. _`Chroma`: https://www.trychroma.com/
.. _`Pinecone`: https://www.pinecone.io/
.. _`Atlas Vector Search`: https://www.mongodb.com/products/platform/atlas-vector-search
.. _`Azure AI Search`: https://azure.microsoft.com/products/ai-services/ai-search
.. _`Cloudflare Vectorize`: https://developers.cloudflare.com/vectorize/
.. _`S3 Vectors`: https://docs.aws.amazon.com/AmazonS3/latest/userguide/s3-vectors.html
.. _`ClickHouse`: https://clickhouse.com/
.. _`Neo4j`: https://neo4j.com/
.. _`Redis`: https://redis.io/docs/latest/develop/interact/search-and-query/advanced-concepts/vectors/
.. _`SurrealDB`: https://surrealdb.com/

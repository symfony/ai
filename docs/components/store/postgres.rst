Postgres Bridge
===============

The Postgres bridge provides vector storage using `pgvector`_, the PostgreSQL extension for vector similarity search.

Requirements
------------

* PostgreSQL 14+
* `pgvector`_ extension installed
* PHP ``ext-pdo`` extension

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-postgres-store

Database Setup
--------------

Enable the pgvector extension and create the table:

.. code-block:: sql

    CREATE EXTENSION IF NOT EXISTS vector;

    CREATE TABLE IF NOT EXISTS documents (
        id UUID PRIMARY KEY,
        embedding vector(1536) NOT NULL,
        metadata JSONB
    );

    -- Recommended index for performance
    CREATE INDEX ON documents USING hnsw (embedding vector_cosine_ops);

Adjust ``1536`` to match the number of dimensions of your embedding model.

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\Postgres\Store;
    use Symfony\AI\Store\Bridge\Postgres\Distance;

    // From a PDO connection
    $pdo = new \PDO('pgsql:host=localhost;dbname=mydb', $user, $password);
    $store = Store::fromPdo($pdo, tableName: 'documents', vectorFieldName: 'embedding', distance: Distance::Cosine);

    // Or from a Doctrine DBAL connection
    $store = Store::fromDbal($connection, tableName: 'documents', vectorFieldName: 'embedding', distance: Distance::Cosine);

**Available distance metrics:**

* ``Distance::Cosine`` (``<=>``) – cosine distance (default, recommended)
* ``Distance::L2`` (``<->``) – Euclidean distance
* ``Distance::InnerProduct`` (``<#>``) – inner product
* ``Distance::L1`` (``<+>``) – Manhattan distance

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            postgres:
                my_store:
                    dsn: '%env(DATABASE_URL)%'
                    table: 'documents'
                    vector_field: 'embedding'
                    distance: 'cosine'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    DATABASE_URL=pgsql://user:password@localhost:5432/mydb

Usage
-----

Adding Documents
~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Store\Document\Metadata;
    use Symfony\AI\Store\Document\VectorDocument;
    use Symfony\AI\Platform\Vector\Vector;
    use Symfony\Component\Uid\Uuid;

    $document = new VectorDocument(
        Uuid::v4(),
        new Vector([0.1, 0.2, /* ... 1536 dimensions */]),
        new Metadata(['source' => 'my-document.txt'])
    );

    $store->add($document);

Querying Documents
~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Store\Query\VectorQuery;

    $results = $store->query(new VectorQuery(new Vector([0.1, 0.2, /* ... */]), maxResults: 10));

    foreach ($results as $document) {
        echo $document->metadata['source'];
    }

Hybrid Search
~~~~~~~~~~~~~

The Postgres store also supports hybrid vector + full-text search via ``HybridQuery``::

    use Symfony\AI\Store\Bridge\Postgres\Query\HybridQuery;

    $results = $store->query(new HybridQuery(
        new Vector([0.1, 0.2, /* ... */]),
        text: 'search term',
        maxResults: 10
    ));

Using ``ai:store:setup``
------------------------

When using the AI Bundle, the store can be initialized automatically:

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store
    $ php bin/console ai:store:drop my_store

Performance Considerations
--------------------------

* Use ``hnsw`` index for better recall with high-dimensional vectors
* Use ``ivfflat`` index for faster indexing with slightly lower recall
* The number of lists for ``ivfflat`` should be roughly ``rows / 1000`` (minimum 100)

.. _`pgvector`: https://github.com/pgvector/pgvector

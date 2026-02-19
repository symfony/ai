ClickHouse Bridge
=================

The ClickHouse bridge provides vector storage using `ClickHouse`_'s built-in cosine distance
function for vector similarity search.

Requirements
------------

* ClickHouse 23.5+ (with vector functions support)

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-click-house-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\ClickHouse\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        databaseName: 'default',
        tableName: 'documents',
    );

The store uses the ClickHouse HTTP interface (port 8123 by default).

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            clickhouse:
                my_store:
                    endpoint: '%env(CLICKHOUSE_URL)%'
                    database: 'default'
                    table: 'documents'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    CLICKHOUSE_URL=http://localhost:8123

Table Setup
-----------

.. code-block:: sql

    CREATE TABLE IF NOT EXISTS documents (
        id UUID,
        embedding Array(Float32),
        metadata String
    ) ENGINE = MergeTree()
    ORDER BY id;

Or use the ``ai:store:setup`` command:

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store
    $ php bin/console ai:store:drop my_store

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
        new Vector([0.1, 0.2, /* ... */]),
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

Similarity is computed using the ``cosineDistance`` function.

Setup with Docker
-----------------

.. code-block:: terminal

    $ docker run -p 8123:8123 -p 9000:9000 clickhouse/clickhouse-server:latest

.. _`ClickHouse`: https://clickhouse.com/

SurrealDB Bridge
================

The SurrealDB bridge provides vector storage using `SurrealDB`_'s MTREE vector index
for similarity search.

Requirements
------------

* SurrealDB 1.5+

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-surreal-db-store

Configuration
-------------

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
        strategy: 'cosine',
        embeddingsDimension: 1536,
    );

**Available distance strategies:** ``cosine``, ``euclidean``, ``manhattan``, ``minkowski``

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            surrealdb:
                my_store:
                    endpoint: '%env(SURREALDB_URL)%'
                    user: '%env(SURREALDB_USER)%'
                    password: '%env(SURREALDB_PASSWORD)%'
                    namespace: 'my_namespace'
                    database: 'my_database'
                    table: 'vectors'
                    vector_field: '_vectors'
                    dimensions: 1536
                    strategy: 'cosine'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    SURREALDB_URL=http://localhost:8000
    SURREALDB_USER=root
    SURREALDB_PASSWORD=root

Table Setup
-----------

The index is created automatically when calling ``setup()``:

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store
    $ php bin/console ai:store:drop my_store

Or create the index manually via SurrealQL:

.. code-block:: text

    DEFINE INDEX vector_idx ON vectors FIELDS _vectors MTREE DIMENSION 1536 DIST COSINE;

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

Setup with Docker
-----------------

.. code-block:: terminal

    $ docker run -p 8000:8000 surrealdb/surrealdb:latest start --user root --pass root

.. _`SurrealDB`: https://surrealdb.com/

MongoDB Atlas Bridge
====================

The MongoDB bridge provides vector storage using `MongoDB Atlas Vector Search`_.

Requirements
------------

* MongoDB Atlas cluster with vector search enabled
* ``mongodb/mongodb`` PHP library

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-mongo-db-store mongodb/mongodb

Index Setup
-----------

Before using the store, create a vector search index in your MongoDB Atlas cluster.
In the Atlas UI, navigate to **Search** → **Create Search Index** → **JSON Editor** and use:

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

Replace ``embedding`` with your vector field name and ``1536`` with your model's embedding dimensions.

Configuration
-------------

.. code-block:: php

    use MongoDB\Client;
    use Symfony\AI\Store\Bridge\MongoDb\Store;

    $client = new Client('mongodb+srv://user:password@cluster.mongodb.net');

    $store = new Store(
        $client,
        databaseName: 'my_database',
        collectionName: 'documents',
        indexName: 'vector_index',
        vectorFieldName: 'embedding',
        embeddingsDimension: 1536,
    );

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
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

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    MONGODB_URI=mongodb+srv://user:password@cluster.mongodb.net

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
        new Metadata(['source' => 'my-document.txt', 'category' => 'docs'])
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

.. note::

    The ``remove()`` operation is not supported by this bridge and throws an exception when called.
    Bulk write operations are enabled by default for better performance.

.. _`MongoDB Atlas Vector Search`: https://www.mongodb.com/products/platform/atlas-vector-search

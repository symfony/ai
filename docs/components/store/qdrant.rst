Qdrant Bridge
=============

The Qdrant bridge provides vector storage using `Qdrant`_, a high-performance vector database
designed for similarity search.

Requirements
------------

* Running Qdrant instance (local or Qdrant Cloud)

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-qdrant-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\Qdrant\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:6333',
        apiKey: '',           // empty for local, required for Qdrant Cloud
        collectionName: 'my_documents',
        embeddingsDimension: 1536,
        embeddingsDistance: 'Cosine',
    );

**Available distance metrics:** ``Cosine``, ``Euclid``, ``Dot``, ``Manhattan``

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            qdrant:
                my_store:
                    endpoint: '%env(QDRANT_URL)%'
                    api_key: '%env(QDRANT_API_KEY)%'
                    collection: 'my_documents'
                    vector_dimension: 1536
                    distance: 'Cosine'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    QDRANT_URL=http://localhost:6333
    QDRANT_API_KEY=your-api-key   # required for Qdrant Cloud

Collection Setup
----------------

The collection is created automatically when calling ``setup()``:

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

    $ docker run -p 6333:6333 -p 6334:6334 qdrant/qdrant

The REST API is available on port 6333, the gRPC API on port 6334.

.. _`Qdrant`: https://qdrant.tech/

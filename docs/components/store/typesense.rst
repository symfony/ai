Typesense Bridge
================

The Typesense bridge provides vector storage using `Typesense`_,
a fast, open-source search engine with built-in vector search support.

Requirements
------------

* Typesense 0.25+

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-typesense-store

Configuration
-------------

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

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

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

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    TYPESENSE_URL=http://localhost:8108
    TYPESENSE_API_KEY=your-api-key

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

Queries use Typesense's ``multi_search`` endpoint with vector search.

Setup with Docker
-----------------

.. code-block:: terminal

    $ docker run -p 8108:8108 \
        -e TYPESENSE_DATA_DIR=/data \
        -e TYPESENSE_API_KEY=your-api-key \
        typesense/typesense:latest --data-dir /data --api-key=your-api-key

.. _`Typesense`: https://typesense.org/

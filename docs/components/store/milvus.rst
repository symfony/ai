Milvus Bridge
=============

The Milvus bridge provides vector storage using `Milvus`_, a cloud-native vector database
built for scalable similarity search.

Requirements
------------

* Running Milvus instance (local or Zilliz Cloud)

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-milvus-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\Milvus\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:19530',
        apiKey: '',           // empty for local, required for Zilliz Cloud
        database: 'default',
        collection: 'my_documents',
        vectorFieldName: 'embedding',
        dimensions: 1536,
        metricType: 'COSINE',
    );

**Available metric types:** ``COSINE``, ``L2``, ``IP``

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            milvus:
                my_store:
                    endpoint: '%env(MILVUS_URL)%'
                    api_key: '%env(MILVUS_API_KEY)%'
                    database: 'default'
                    collection: 'my_documents'
                    vector_field: 'embedding'
                    dimensions: 1536
                    metric_type: 'COSINE'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    MILVUS_URL=http://localhost:19530
    MILVUS_API_KEY=your-api-key   # required for Zilliz Cloud

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

    $ docker run -p 19530:19530 -p 9091:9091 milvusdb/milvus:latest standalone

.. _`Milvus`: https://milvus.io/

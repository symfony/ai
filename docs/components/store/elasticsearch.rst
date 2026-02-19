Elasticsearch Bridge
====================

The Elasticsearch bridge provides vector storage using `Elasticsearch`_'s ``dense_vector`` field type
for approximate nearest-neighbor (ANN) similarity search.

Requirements
------------

* Elasticsearch 8.0+
* Elasticsearch Basic license or higher (vector search is available on all tiers)

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-elasticsearch-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\Elasticsearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpoint: 'https://localhost:9200',
        indexName: 'my_documents',
        vectorsField: 'embedding',
        dimensions: 1536,
        similarity: 'cosine',
    );

**Available similarity metrics:** ``cosine``, ``dot_product``, ``l2_norm``

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            elasticsearch:
                my_store:
                    endpoint: '%env(ELASTICSEARCH_URL)%'
                    index: 'my_documents'
                    vector_field: 'embedding'
                    dimensions: 1536
                    similarity: 'cosine'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    ELASTICSEARCH_URL=https://localhost:9200

Index Setup
-----------

The index is created automatically when calling ``setup()``:

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store

Or create the index manually:

.. code-block:: json

    {
        "mappings": {
            "properties": {
                "embedding": {
                    "type": "dense_vector",
                    "dims": 1536,
                    "index": true,
                    "similarity": "cosine"
                },
                "metadata": {
                    "type": "object"
                }
            }
        }
    }

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

    $ docker run -p 9200:9200 -e "discovery.type=single-node" \
        -e "xpack.security.enabled=false" \
        docker.elastic.co/elasticsearch/elasticsearch:8.17.0

.. _`Elasticsearch`: https://www.elastic.co/elasticsearch

OpenSearch Bridge
=================

The OpenSearch bridge provides vector storage using `OpenSearch`_'s k-NN plugin for vector similarity search.

Requirements
------------

* OpenSearch 2.0+ with the k-NN plugin enabled (included by default)

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-open-search-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\OpenSearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpoint: 'https://localhost:9200',
        indexName: 'my_documents',
        vectorsField: 'embedding',
        dimensions: 1536,
        spaceType: 'cosinesimil',
    );

**Available space types:** ``cosinesimil``, ``l2``, ``innerproduct``, ``hamming``

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            opensearch:
                my_store:
                    endpoint: '%env(OPENSEARCH_URL)%'
                    index: 'my_documents'
                    vector_field: 'embedding'
                    dimensions: 1536
                    space_type: 'cosinesimil'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    OPENSEARCH_URL=https://localhost:9200

Index Setup
-----------

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store

Or create the index manually:

.. code-block:: json

    {
        "settings": {
            "index.knn": true
        },
        "mappings": {
            "properties": {
                "embedding": {
                    "type": "knn_vector",
                    "dimension": 1536,
                    "space_type": "cosinesimil"
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
        -e "DISABLE_SECURITY_PLUGIN=true" \
        opensearchproject/opensearch:latest

.. _`OpenSearch`: https://opensearch.org/

ManticoreSearch Bridge
======================

The ManticoreSearch bridge provides vector storage using `ManticoreSearch`_,
an open-source search engine with HNSW-based vector similarity search.

Requirements
------------

* ManticoreSearch 6.2+

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-manticore-search-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\ManticoreSearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        host: 'http://localhost:9308',
        table: 'documents',
        field: 'embedding',
        type: 'hnsw',
        similarity: 'cosine',
        dimensions: 1536,
        quantization: 'none',
    );

**Available similarity metrics:** ``cosine``, ``l2``

**Available quantization options:** ``none``, ``pq``, ``sq``

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            manticoresearch:
                my_store:
                    host: '%env(MANTICORESEARCH_URL)%'
                    table: 'documents'
                    field: 'embedding'
                    dimensions: 1536
                    similarity: 'cosine'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    MANTICORESEARCH_URL=http://localhost:9308

Table Setup
-----------

.. code-block:: sql

    CREATE TABLE documents (
        id bigint,
        embedding float_vector knn_type='hnsw' knn_dims='1536' hnsw_similarity='cosine',
        metadata text
    );

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

    $ docker run -p 9306:9306 -p 9308:9308 manticoresearch/manticore:latest

.. _`ManticoreSearch`: https://manticoresearch.com/

Meilisearch Bridge
==================

The Meilisearch bridge provides vector storage using `Meilisearch`_'s hybrid search capabilities,
combining full-text and semantic (vector) search.

Requirements
------------

* Meilisearch 1.6+ with vector store enabled
* Meilisearch API key

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-meilisearch-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\Meilisearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:7700',
        apiKey: 'masterKey',
        indexName: 'documents',
        embedder: 'default',
        vectorFieldName: '_vectors',
        embeddingsDimension: 1536,
        semanticRatio: 0.5,     // balance between semantic and keyword search
    );

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            meilisearch:
                my_store:
                    endpoint: '%env(MEILISEARCH_URL)%'
                    api_key: '%env(MEILISEARCH_API_KEY)%'
                    index: 'documents'
                    embedder: 'default'
                    vector_field: '_vectors'
                    dimensions: 1536
                    semantic_ratio: 0.5

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    MEILISEARCH_URL=http://localhost:7700
    MEILISEARCH_API_KEY=masterKey

Index Setup
-----------

Enable the experimental vector store feature and configure the embedder:

.. code-block:: bash

    # Enable vector store feature
    curl -X PATCH 'http://localhost:7700/experimental-features/' \
      -H 'Authorization: Bearer masterKey' \
      -H 'Content-Type: application/json' \
      --data '{"vectorStore": true}'

The index and embedder configuration are created automatically via ``setup()``:

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store

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

The ``semanticRatio`` parameter (0.0–1.0) controls the balance between vector and keyword search:

* ``1.0`` – pure semantic (vector) search
* ``0.0`` – pure keyword (full-text) search
* ``0.5`` – equal balance (default)

Setup with Docker
-----------------

.. code-block:: terminal

    $ docker run -p 7700:7700 getmeili/meilisearch:latest

.. _`Meilisearch`: https://www.meilisearch.com/

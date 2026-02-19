Weaviate Bridge
===============

The Weaviate bridge provides vector storage using `Weaviate`_, an open-source vector database
with a GraphQL-based query interface.

Requirements
------------

* Running Weaviate instance (local or Weaviate Cloud)

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-weaviate-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\Weaviate\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:8080',
        apiKey: '',           // empty for local, required for Weaviate Cloud
        collection: 'Document',
    );

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            weaviate:
                my_store:
                    endpoint: '%env(WEAVIATE_URL)%'
                    api_key: '%env(WEAVIATE_API_KEY)%'
                    collection: 'Document'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    WEAVIATE_URL=http://localhost:8080
    WEAVIATE_API_KEY=your-api-key   # required for Weaviate Cloud

Collection Setup
----------------

The collection is created automatically when calling ``setup()``:

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store
    $ php bin/console ai:store:drop my_store

.. note::

    Weaviate collection names must start with an uppercase letter.
    The store checks whether the collection already exists before attempting to create it.

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

Setup with Docker
-----------------

.. code-block:: terminal

    $ docker run -p 8080:8080 -p 50051:50051 cr.weaviate.io/semitechnologies/weaviate:latest

.. _`Weaviate`: https://weaviate.io/

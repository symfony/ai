Pinecone Bridge
===============

The Pinecone bridge provides vector storage using `Pinecone`_, a managed vector database service.

Requirements
------------

* Pinecone account and API key
* ``probots-io/pinecone-php`` PHP client

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-pinecone-store probots-io/pinecone-php

Configuration
-------------

.. code-block:: php

    use Probots\Pinecone\Client as PineconeClient;
    use Symfony\AI\Store\Bridge\Pinecone\Store;

    $pinecone = new PineconeClient($apiKey);

    $store = new Store(
        $pinecone,
        indexName: 'my-index',
        namespace: 'default',
        filter: [],
        topK: 10,
    );

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            pinecone:
                my_store:
                    api_key: '%env(PINECONE_API_KEY)%'
                    index: 'my-index'
                    namespace: 'default'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    PINECONE_API_KEY=your-pinecone-api-key

Index Setup
-----------

Create a Pinecone index before using the store. The number of dimensions must match your embedding model:

.. code-block:: php

    $pinecone->index()->createServerless(
        name: 'my-index',
        dimension: 1536,     // e.g. 1536 for text-embedding-3-small
        metric: 'cosine',
        cloud: 'aws',
        region: 'us-east-1',
    );

Or create the index via the `Pinecone Console`_.

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

Namespaces
----------

Namespaces isolate data within a single index and are useful for multi-tenant applications:

.. code-block:: php

    $store = new Store($pinecone, 'my-index', namespace: 'tenant-123');

.. note::

    Deletion supports batches of up to 1000 IDs per request.

.. _`Pinecone`: https://www.pinecone.io/
.. _`Pinecone Console`: https://app.pinecone.io/

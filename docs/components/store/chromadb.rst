ChromaDB Bridge
===============

The ChromaDB bridge provides vector storage using `ChromaDB`_, an open-source AI-native vector database.

Requirements
------------

* Running ChromaDB instance (local or hosted)
* ``codewithkyrian/chromadb-php`` PHP client

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-chroma-db-store codewithkyrian/chromadb-php

Configuration
-------------

.. code-block:: php

    use Codewithkyrian\ChromaDB\ChromaDB;
    use Symfony\AI\Store\Bridge\ChromaDb\Store;

    $client = ChromaDB::client(host: 'localhost', port: 8000);

    $store = new Store($client, collectionName: 'my_documents');

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            chromadb:
                my_store:
                    collection: 'my_documents'
                    host: 'localhost'
                    port: 8000

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

Using the Indexer
~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\AI\Store\Indexer;

    $indexer = new Indexer($platform, $embeddingModel, $store);
    $indexer->index(new TextDocument(Uuid::v4(), 'Content to index...'));

.. note::

    The collection is created automatically the first time documents are added.

Setup
-----

Run ChromaDB locally with Docker:

.. code-block:: terminal

    $ docker run -p 8000:8000 chromadb/chroma

Or use `Chroma Cloud`_ for a managed solution.

.. _`ChromaDB`: https://www.trychroma.com/
.. _`Chroma Cloud`: https://www.trychroma.com/cloud

Redis Bridge
============

The Redis bridge provides vector storage using `Redis Stack`_ with the RediSearch module,
which enables vector similarity search over stored documents.

Requirements
------------

* Redis Stack (includes RediSearch) or Redis Cloud
* PHP ``ext-redis`` extension

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-redis-store

Setup
-----

Run Redis Stack locally with Docker:

.. code-block:: terminal

    $ docker run -p 6379:6379 redis/redis-stack-server:latest

Or use `Redis Cloud`_ for a managed solution.

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\Redis\Store;
    use Symfony\AI\Store\Bridge\Redis\Distance;

    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);

    $store = new Store(
        $redis,
        indexName: 'my_index',
        keyPrefix: 'vector:',
        distance: Distance::Cosine,
    );

**Available distance metrics:**

* ``Distance::Cosine`` (default)
* ``Distance::L2`` – Euclidean distance
* ``Distance::IP`` – Inner product

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            redis:
                my_store:
                    host: '127.0.0.1'
                    port: 6379
                    index: 'my_index'
                    prefix: 'vector:'
                    distance: 'cosine'

Index Setup
-----------

The index is created automatically when you call ``setup()`` on the store or use the
``ai:store:setup`` console command. It uses the ``FT.CREATE`` command to create a
JSON-based index with a ``FLAT`` (exact search) or ``HNSW`` vector field.

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

.. note::

    Documents are stored as JSON objects under the configured ``keyPrefix``.
    KNN (K-Nearest Neighbor) queries are used for vector similarity search.

.. _`Redis Stack`: https://redis.io/docs/stack/
.. _`Redis Cloud`: https://redis.com/redis-enterprise-cloud/overview/

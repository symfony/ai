MongoDB Bridge
==============

The MongoDB bridge provides vector storage and similarity search capabilities
using `Atlas Vector Search`_.

.. note::

    This bridge requires a `MongoDB Atlas`_ cluster or the
    `mongodb-atlas-local`_ Docker image for local development.
    Standard self-hosted MongoDB deployments do not support Atlas Vector Search.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-mongo-db-store

This package requires the ``ext-mongodb`` PHP extension.

Atlas Search Index Setup
------------------------

The MongoDB Atlas store requires a **Vector Search index** on your collection.
You can create it programmatically using the ``setup()`` method (see `Setup`_ below),
or manually through the `Atlas UI`_ or the Atlas Administration API.

The default index definition looks like this:

.. code-block:: json

    {
        "fields": [
            {
                "numDimensions": 1536,
                "path": "vector",
                "similarity": "euclidean",
                "type": "vector"
            }
        ]
    }

Adjust ``numDimensions`` to match your embedding model's output size,
``path`` to match your ``vectorFieldName``, and ``similarity`` to the
distance metric of your choice.

Configuration
-------------

Basic Configuration
~~~~~~~~~~~~~~~~~~~

::

    use MongoDB\Client;
    use Symfony\AI\Store\Bridge\MongoDb\Store;

    $store = new Store(
        client: new Client('mongodb+srv://user:pass@cluster.mongodb.net'),
        databaseName: 'my_database',
        collectionName: 'documents',
        indexName: 'vector_index',
        vectorFieldName: 'vector',        // default: 'vector'
        bulkWrite: false,                 // default: false
        embeddingsDimension: 1536,        // default: 1536
    );

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            mongodb:
                my_mongodb_store:
                    database: 'my_database'
                    collection: 'documents'       # optional, defaults to store name
                    index_name: 'vector_index'
                    vector_field: 'vector'        # optional, default: vector
                    bulk_write: false             # optional, default: false
                    setup_options:
                        fields: []                # optional extra index fields

By default, the bundle uses a ``MongoDB\Client`` service registered in the
container. Register the client as a service with the connection string:

.. code-block:: yaml

    # config/services.yaml
    services:
        MongoDB\Client:
            arguments:
                - '%env(MONGODB_URL)%'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    # .env.local
    MONGODB_URL=mongodb+srv://user:pass@cluster.mongodb.net

Usage
-----

Setup
~~~~~

Call ``setup()`` to create the collection and its Atlas Vector Search index.
If either already exists the call is a no-op (existing resources are preserved):

::

    $store->setup();

Pass additional field definitions to include extra fields in the search index:

::

    $store->setup([
        'fields' => [
            [
                'path' => 'title',
                'type' => 'string',
            ],
        ],
    ]);

Adding Documents
~~~~~~~~~~~~~~~~

::

    use Symfony\AI\Platform\Vector\Vector;
    use Symfony\AI\Store\Document\Metadata;
    use Symfony\AI\Store\Document\VectorDocument;
    use Symfony\Component\Uid\Uuid;

    $document = new VectorDocument(
        id: Uuid::v4(),
        vector: new Vector([0.1, 0.2, 0.3, /* ... */]),
        metadata: new Metadata(['title' => 'My Document', 'category' => 'example']),
    );

    $store->add($document);

Pass an array to add multiple documents at once:

::

    $store->add([$document1, $document2]);

Documents are upserted by ID, so calling ``add()`` again with the same ID updates
the existing document.

Querying Documents
~~~~~~~~~~~~~~~~~~

::

    use Symfony\AI\Store\Query\VectorQuery;

    $results = $store->query(new VectorQuery($queryVector));

    foreach ($results as $document) {
        echo $document->getId() . ' (score: ' . $document->getScore() . ")\n";
        echo json_encode($document->getMetadata()->getArrayCopy()) . "\n";
    }

Query Options
~~~~~~~~~~~~~

The following options can be passed as the second argument to ``query()``:

* ``limit`` (``int``, default: ``5``) — maximum number of results to return
* ``numCandidates`` (``int``, default: ``200``) — number of nearest-neighbor candidates
  to evaluate; higher values improve recall at the cost of latency
* ``filter`` (``array``) — a MongoDB match expression applied **before** the
  vector search (pre-filter); requires a corresponding filter field in the index
* ``minScore`` (``float``) — minimum similarity score; results below this threshold
  are excluded from the output

::

    $results = $store->query(new VectorQuery($queryVector), [
        'limit' => 10,
        'numCandidates' => 500,
        'filter' => ['category' => 'example'],
        'minScore' => 0.8,
    ]);

Removing Documents
~~~~~~~~~~~~~~~~~~

::

    $store->remove($document->getId());

    // Or remove multiple documents at once:
    $store->remove([$id1, $id2, $id3]);

Dropping the Collection
~~~~~~~~~~~~~~~~~~~~~~~

::

    $store->drop();

Bulk Write Mode
---------------

By default, each ``add()`` or ``remove()`` call issues one database operation per
document. Enable bulk write mode to batch all operations into a single round-trip,
which reduces latency when inserting or deleting large numbers of documents:

::

    $store = new Store(
        $client,
        'my_database',
        'documents',
        'vector_index',
        bulkWrite: true,
    );

    // All documents are sent in a single bulkWrite call:
    $store->add([$document1, $document2, $document3]);

In the bundle configuration:

.. code-block:: yaml

    ai:
        store:
            mongodb:
                my_mongodb_store:
                    database: 'my_database'
                    index_name: 'vector_index'
                    bulk_write: true

Similarity Metrics
------------------

The similarity metric is set on the Atlas Search index, not in the PHP code.
Choose the metric that matches how your embedding model was trained:

* ``euclidean`` — Euclidean (L2) distance; good general-purpose default
* ``cosine`` — Cosine similarity; recommended for most text embeddings
* ``dotProduct`` — Dot product; use when vectors are already normalized

Change the ``similarity`` field in the Atlas Search index definition to switch
metrics. Refer to the `Atlas Vector Search field types documentation`_ for details.

Local Development
-----------------

For local development and testing you can use the `mongodb-atlas-local`_ Docker
image, which provides Atlas Vector Search support without a cloud cluster:

.. code-block:: yaml

    # compose.yaml
    services:
        mongodb:
            image: mongodb/mongodb-atlas-local:8.0
            environment:
                MONGODB_INITDB_DATABASE: my_database
            ports:
                - '27017:27017'

Then connect with:

.. code-block:: bash

    MONGODB_URL=mongodb://localhost:27017

Limitations
-----------

* Requires MongoDB Atlas or the local Atlas emulator — standard MongoDB does not
  support Atlas Vector Search
* The Atlas Search index must be in the ``READY`` state before queries return
  results; index builds are asynchronous and may take a moment after ``setup()``
* The ``filter`` query option requires the filtered fields to be declared in the
  Atlas Search index definition
* Vector dimensions must be consistent across all documents in the same index

.. _`Atlas Vector Search`: https://www.mongodb.com/docs/atlas/atlas-vector-search/vector-search-overview/
.. _`MongoDB Atlas`: https://www.mongodb.com/atlas
.. _`mongodb-atlas-local`: https://hub.docker.com/r/mongodb/mongodb-atlas-local
.. _`Atlas UI`: https://www.mongodb.com/docs/atlas/atlas-vector-search/create-index/
.. _`Atlas Vector Search field types documentation`: https://www.mongodb.com/docs/atlas/atlas-search/field-types/knn-vector/#define-the-index-for-the-fts-field-type-type

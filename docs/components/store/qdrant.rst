Qdrant Bridge
=============

The Qdrant bridge provides vector storage using `Qdrant`_ with optional hybrid search
combining dense vectors and BM25 sparse vectors via native RRF fusion.

Dense vector search understands meaning ("green ogre" finds Shrek) but can miss exact
keyword matches. BM25 sparse vectors handle precise term matching. Hybrid search combines
both using Qdrant's built-in `Reciprocal Rank Fusion`_ (RRF), so documents that match
semantically **and** lexically rank higher.

Requirements
------------

* `Qdrant`_ v1.10+ (for sparse vectors and Query API fusion)
* An HTTP client (``symfony/http-client``)

Setup
-----

Run the setup command to create the collection:

.. code-block:: bash

    php bin/console ai:store:setup ai.store.qdrant.my_store

In hybrid mode, the collection is created with named dense vectors and a sparse vector
field with ``idf`` modifier for BM25 normalization. In non-hybrid mode, a standard
unnamed vector is used.

Configuration
-------------

Vector-only:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            qdrant:
                my_store:
                    endpoint: '%env(QDRANT_URL)%'
                    api_key: '%env(QDRANT_API_KEY)%'
                    collection_name: 'documents'
                    dimensions: 768
                    distance: 'Cosine'

Hybrid:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            qdrant:
                my_store:
                    endpoint: '%env(QDRANT_URL)%'
                    api_key: '%env(QDRANT_API_KEY)%'
                    collection_name: 'documents'
                    dimensions: 768
                    distance: 'Cosine'
                    hybrid_enabled: true

When ``hybrid_enabled`` is ``false`` (default), behavior is identical to the standard
Qdrant store.

The ``dsn`` value can reference environment variables:

.. code-block:: bash

    # .env.local
    QDRANT_URL=http://localhost:6333
    QDRANT_API_KEY=your-api-key

Usage
-----

Adding Documents
~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Store\Document\Metadata;
    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\AI\Store\Indexer\DocumentIndexer;
    use Symfony\AI\Store\Indexer\DocumentProcessor;
    use Symfony\Component\Uid\Uuid;

    $documents = [
        new TextDocument(
            id: Uuid::v4(),
            content: 'Document text used for embedding and BM25 indexing.',
            metadata: new Metadata(['title' => 'My Document']),
        ),
    ];

    // $vectorizer and $store are injected services
    $indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store));
    $indexer->index($documents);

In hybrid mode, each document is stored with both a dense vector (from the embedding)
and a sparse BM25 vector (term frequencies computed from the ``_text`` metadata).

Querying Documents
~~~~~~~~~~~~~~~~~~

Vector-only search:

.. code-block:: php

    use Symfony\AI\Store\Query\VectorQuery;

    $results = $store->query(new VectorQuery($embedding), ['limit' => 10]);

Hybrid search (dense + BM25 sparse):

.. code-block:: php

    use Symfony\AI\Store\Query\HybridQuery;

    $results = $store->query(
        new HybridQuery($embedding, 'search terms'),
        ['limit' => 10],
    );

    foreach ($results as $document) {
        $metadata = $document->getMetadata()->getArrayCopy();
        echo $metadata['title'].' (Score: '.$document->getScore().')'.\PHP_EOL;
    }

How It Works
------------

When a ``HybridQuery`` is submitted, the store sends a Qdrant Query API request with
two prefetch stages:

1. **Sparse BM25 prefetch**: The query text is tokenized client-side into term
   frequencies. Qdrant applies IDF normalization server-side via the ``modifier: idf``
   configuration on the sparse vector field.

2. **Dense vector prefetch**: The embedding vector is searched against the named dense
   vector field.

Both prefetch results are merged using Qdrant's native ``rrf`` fusion. The prefetch
limit is set to ``3x`` the requested limit to ensure enough candidates for fusion.

When a ``VectorQuery`` is used in hybrid mode, only the dense vector is searched
(using the named vector field).

.. note::

    The ``semanticRatio`` parameter of ``HybridQuery`` is ignored by the Qdrant store.
    Qdrant handles fusion natively with equal weight for both prefetch stages. The
    Postgres store uses ``semanticRatio`` to control the balance because it implements
    RRF in PHP.

Configuration Options
---------------------

* ``hybrid_enabled`` (bool, default: ``false``): Enable hybrid search with BM25 sparse vectors
* ``dense_vector_name`` (string, default: ``'dense'``): Name of the dense vector field in the collection
* ``sparse_vector_name`` (string, default: ``'bm25'``): Name of the sparse vector field in the collection
* ``dimensions`` (int, default: ``1536``): Embedding vector dimensions
* ``distance`` (string, default: ``'Cosine'``): Distance metric (``Cosine``, ``Euclid``, ``Dot``)
* ``async`` (bool): Use asynchronous writes

.. _`Qdrant`: https://qdrant.tech/
.. _`Reciprocal Rank Fusion`: https://plg.uwaterloo.ca/~gvcormac/cormacksigir09-rrf.pdf

Postgres Bridge
===============

The Postgres bridge provides vector storage using `pgvector`_ with optional hybrid search
combining semantic similarity and full-text search via `Reciprocal Rank Fusion`_ (RRF).

Vector search alone understands meaning ("green ogre" finds Shrek) but can miss exact
keyword matches that users expect. Full-text search handles precise terms well but has
no semantic understanding. Hybrid search combines both: documents that match semantically
**and** lexically rank higher, producing more relevant results in practice.

Two store implementations are available:

* ``Store``: Vector-only search (pgvector)
* ``HybridStore``: Combines vector search with full-text search and optional fuzzy matching

Requirements
------------

* PostgreSQL 14+
* `pgvector`_ extension
* ``ext-pdo_pgsql`` PHP extension

By default, hybrid search uses PostgreSQL's built-in full-text search (``ts_rank_cd``
with ``tsvector``/``tsquery``). This requires no additional extension.

For better ranking quality, you can opt into BM25 via `plpgsql_bm25`_, which adds term
frequency saturation and document length normalization. The extension is automatically
installed when running ``ai:store:setup``.

Optional extensions:

* `pg_trgm`_: Enables fuzzy matching for typo tolerance
* `plpgsql_bm25`_: Enables BM25 ranking (alternative to native FTS)

Setup
-----

Run the setup command to create the table, indexes, and install required extensions:

.. code-block:: bash

    php bin/console ai:store:setup ai.store.postgres.my_store

This automatically creates the table with vector, content, and metadata columns,
installs an HNSW index for vector similarity, and a GIN index for full-text search
in hybrid mode. If BM25 strategy is configured, the `plpgsql_bm25`_ functions are
installed as well.

Configuration
-------------

Vector-only:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            postgres:
                my_store:
                    dsn: '%env(DATABASE_URL)%'
                    table_name: 'documents'
                    vector_field: 'embedding'
                    distance: cosine

Hybrid:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            postgres:
                my_store:
                    dsn: '%env(DATABASE_URL)%'
                    table_name: 'documents'
                    vector_field: 'embedding'
                    distance: cosine

                    hybrid:
                        enabled: true
                        content_field: 'content'
                        semantic_ratio: 0.5
                        language: 'english'
                        text_search_strategy: 'bm25'
                        bm25_language: 'en'

When ``hybrid.enabled`` is ``false`` (default), the bundle uses the vector-only ``Store``.

The ``dsn`` value can reference an environment variable:

.. code-block:: bash

    # .env.local
    DATABASE_URL=pgsql://user:password@localhost:5432/mydb

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
            content: 'Document text used for embedding and full-text search.',
            metadata: new Metadata(['title' => 'My Document']),
        ),
    ];

    // $vectorizer and $store are injected services
    $indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store));
    $indexer->index($documents);

The ``DocumentProcessor`` vectorizes the content and stores the original text in
metadata (under the ``_text`` key) for full-text indexing.

Querying Documents
~~~~~~~~~~~~~~~~~~

Vector-only search:

.. code-block:: php

    use Symfony\AI\Store\Query\VectorQuery;

    $results = $store->query(new VectorQuery($embedding), ['limit' => 10]);

Hybrid search (vector + full-text):

.. code-block:: php

    use Symfony\AI\Store\Query\HybridQuery;

    $results = $store->query(
        new HybridQuery($embedding, 'search terms', semanticRatio: 0.5),
        ['limit' => 10],
    );

    foreach ($results as $document) {
        $metadata = $document->getMetadata()->getArrayCopy();
        echo $metadata['title'].' (Score: '.$document->getScore().')'.\PHP_EOL;
    }

The ``semanticRatio`` controls the balance between search methods:

* ``0.0``: Full-text search only (keyword matching)
* ``0.5``: Balanced hybrid (RRF fusion of both rankings)
* ``1.0``: Vector similarity only (semantic search)

Text Search Strategies
----------------------

Native PostgreSQL FTS (default)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Uses ``ts_rank_cd`` with ``tsvector``/``tsquery``. Works with any PostgreSQL installation,
no additional extension required. This is the default when no ``text_search_strategy``
is configured:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            postgres:
                my_store:
                    hybrid:
                        enabled: true
                        content_field: 'content'

BM25 Ranking
~~~~~~~~~~~~

Uses `plpgsql_bm25`_ for relevance ranking with term frequency saturation and document
length normalization. Produces better ranking quality than native FTS -- which is why
Elasticsearch, Meilisearch, and Lucene all use BM25.

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            postgres:
                my_store:
                    hybrid:
                        enabled: true
                        content_field: 'content'
                        text_search_strategy: 'bm25'
                        bm25_language: 'en'

The ``plpgsql_bm25`` functions are automatically installed during ``ai:store:setup``.

Reciprocal Rank Fusion
----------------------

RRF merges rankings from different search methods into a single score. It works on
**ranks**, not raw scores -- this avoids normalization issues since BM25 scores and
cosine distances live on completely different scales.

.. code-block:: text

    score(d) = 1/(k + vector_rank) + 1/(k + fts_rank)

The ``rrf_k`` parameter (default: 60) controls how much advantage top-ranked results
get. Lower values amplify the gap between ranks.

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            postgres:
                my_store:
                    hybrid:
                        enabled: true
                        rrf_k: 10
                        normalize_scores: true

When ``normalize_scores`` is enabled (default), scores are scaled to a 0-100 range.

Fuzzy Matching
--------------

Optional typo-tolerant matching via ``pg_trgm`` word similarity. Disabled by default
(``fuzzy_weight: 0.0``).

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            postgres:
                my_store:
                    hybrid:
                        enabled: true
                        fuzzy_weight: 0.3
                        fuzzy_threshold: 0.2

When enabled, queries like ``"spiderman"`` can match ``"Spider-Man"`` even without
exact term overlap. The ``fuzzy_threshold`` sets the minimum ``word_similarity()``
score required for a match.

Distance Metrics
----------------

* ``cosine``: Cosine distance (recommended for normalized embeddings)
* ``l2``: Euclidean distance (default)
* ``inner_product``: Inner product distance

.. _`pgvector`: https://github.com/pgvector/pgvector
.. _`pg_trgm`: https://www.postgresql.org/docs/current/pgtrgm.html
.. _`plpgsql_bm25`: https://github.com/jankovicsandras/plpgsql_bm25
.. _`Reciprocal Rank Fusion`: https://plg.uwaterloo.ca/~gvcormac/cormacksigir09-rrf.pdf

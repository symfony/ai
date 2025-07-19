Symfony AI - Store Component
============================

The Store component provides a low-level abstraction for storing and retrieving documents in a vector store.

Installation
------------

Install the component using Composer:

.. code-block:: terminal

    composer require symfony/ai-store

Purpose
-------

A typical use-case in agentic applications is a dynamic context-extension with similar and useful information, for so
called `Retrieval Augmented Generation`_ (RAG). The Store component implements low-level interfaces, that can be
implemented by different concrete and vendor-specific implementations, so called bridges.
On top of those bridges, the Store component provides higher level features to populate and query those stores with and
for documents.

Indexing
--------

One higher level feature is the ``Symfony\AI\Store\Indexer``. The purpose of this service is to populate a store with documents.
Therefore it accepts one or multiple ``Symfony\AI\Store\Document\TextDocument`` objects, converts them into embeddings and stores them in the
used vector store::

    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\AI\Store\Indexer;

    $indexer = new Indexer($platform, $model, $store);
    $document = new TextDocument('This is a sample document.');
    $indexer->index($document);

You can find more advanced usage in combination with an Agent using the store for RAG in the examples folder:

* `Similarity Search with MariaDB (RAG)`_
* `Similarity Search with MongoDB (RAG)`_
* `Similarity Search with Pinecone (RAG)`_
* `Similarity Search with Meilisearch (RAG)`_
* `Similarity Search with SurrealDB (RAG)`_
* `Similarity Search with memory storage (RAG)`_
* `Similarity Search with Qdrant (RAG)`_

Supported Stores
----------------

* `Azure AI Search`_
* `Chroma`_ (requires `codewithkyrian/chromadb-php` as additional dependency)
* `MariaDB`_ (requires `ext-pdo`)
* `MongoDB Atlas`_ (requires `mongodb/mongodb` as additional dependency)
* `Pinecone`_ (requires `probots-io/pinecone-php` as additional dependency)
* `Postgres`_ (requires `ext-pdo`)
* `Meilisearch`_
* `SurrealDB`_
* `InMemory`_
* `Qdrant`_

.. note::

    See `GitHub`_ for planned stores.

Implementing a Bridge
---------------------

The main extension points of the Store component are

* ``Symfony\AI\Store\StoreInterface`` - Takes care of adding documents to the store.
* ``Symfony\AI\Store\VectorStoreInterface`` - Takes care of querying the store for documents.

This leads to a store implementing two methods::

    use Symfony\AI\Store\StoreInterface;
    use Symfony\AI\Store\VectorStoreInterface;

    class MyStore implements StoreInterface, VectorStoreInterface
    {
        public function add(VectorDocument ...$documents): void
        {
            // Implementation to add a document to the store
        }

        public function query(Vector $vector, array $options = [], ?float $minScore = null): array
        {
            // Implementation to query the store for documents
            return [];
        }
    }

.. _`Retrieval Augmented Generation`: https://de.wikipedia.org/wiki/Retrieval-Augmented_Generation
.. _`Similarity Search with MariaDB (RAG)`: https://github.com/symfony/ai/blob/main/examples/store/mariadb-similarity-search.php
.. _`Similarity Search with MongoDB (RAG)`: https://github.com/symfony/ai/blob/main/examples/store/mongodb-similarity-search.php
.. _`Similarity Search with Pinecone (RAG)`: https://github.com/symfony/ai/blob/main/examples/store/pinecone-similarity-search.php
.. _`Similarity Search with Meilisearch (RAG)`: https://github.com/symfony/ai/blob/main/examples/store/meilisearch-similarity-search.php
.. _`Similarity Search with SurrealDB (RAG)`: https://github.com/symfony/ai/blob/main/examples/store/surrealdb-similarity-search.php
.. _`Similarity Search with memory storage (RAG)`: https://github.com/symfony/ai/blob/main/examples/store/memory-similarity-search.php
.. _`Similarity Search with Qdrant (RAG)`: https://github.com/symfony/ai/blob/main/examples/store/qdrant-similarity-search.php
.. _`Azure AI Search`: https://azure.microsoft.com/products/ai-services/ai-search
.. _`Chroma`: https://www.trychroma.com/
.. _`MariaDB`: https://mariadb.org/projects/mariadb-vector/
.. _`MongoDB Atlas`: https://www.mongodb.com/atlas
.. _`Pinecone`: https://www.pinecone.io/
.. _`Postgres`: https://www.postgresql.org/about/news/pgvector-070-released-2852/
.. _`Meilisearch`: https://www.meilisearch.com/
.. _`SurrealDB`: https://surrealdb.com/
.. _`InMemory`: https://www.php.net/manual/en/language.types.array.php
.. _`Qdrant`: https://qdrant.tech/
.. _`GitHub`: https://github.com/symfony/ai/issues/16

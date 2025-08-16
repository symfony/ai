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
* `Similarity Search with Meilisearch (RAG)`_
* `Similarity Search with memory storage (RAG)`_
* `Similarity Search with Milvus (RAG)`_
* `Similarity Search with MongoDB (RAG)`_
* `Similarity Search with Neo4j (RAG)`_
* `Similarity Search with Pinecone (RAG)`_
* `Similarity Search with Qdrant (RAG)`_
* `Similarity Search with SurrealDB (RAG)`_
* `Similarity Search with Symfony Cache (RAG)`_
* `Similarity Search with Typesense (RAG)`_

.. note::

    Both `InMemory` and `PSR-6 cache` vector stores will load all the data into the
    memory of the PHP process. They can be used only the amount of data fits in the
    PHP memory limit, typically for testing.

Supported Stores
----------------

* `Azure AI Search`_
* `Chroma`_ (requires `codewithkyrian/chromadb-php` as additional dependency)
* `InMemory`_
* `MariaDB`_ (requires `ext-pdo`)
* `Meilisearch`_
* `Milvus`_
* `MongoDB Atlas`_ (requires `mongodb/mongodb` as additional dependency)
* `Neo4j`_
* `Pinecone`_ (requires `probots-io/pinecone-php` as additional dependency)
* `Postgres`_ (requires `ext-pdo`)
* `Qdrant`_
* `SurrealDB`_
* `Symfony Cache`_
* `Typesense`_

.. note::

    See `GitHub`_ for planned stores.

Implementing a Bridge
---------------------

The main extension points of the Store component is the ``Symfony\AI\Store\StoreInterface``, that defines the methods
for adding vectorized documents to the store, and querying the store for documents with a vector.

This leads to a store implementing two methods::

    use Symfony\AI\Store\StoreInterface;

    class MyStore implements StoreInterface
    {
        public function add(VectorDocument ...$documents): void
        {
            // Implementation to add a document to the store
        }

        public function query(Vector $vector, array $options = []): array
        {
            // Implementation to query the store for documents
            return $documents;
        }
    }

.. _`Retrieval Augmented Generation`: https://de.wikipedia.org/wiki/Retrieval-Augmented_Generation
.. _`Similarity Search with MariaDB (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/mariadb-gemini.php
.. _`Similarity Search with Meilisearch (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/meilisearch.php
.. _`Similarity Search with memory storage (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/in-memory.php
.. _`Similarity Search with Milvus (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/meilisearch.php
.. _`Similarity Search with MongoDB (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/milvus.php
.. _`Similarity Search with Neo4j (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/neo4j.php
.. _`Similarity Search with Pinecone (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/pinecone.php
.. _`Similarity Search with Symfony Cache (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/cache.php
.. _`Similarity Search with Qdrant (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/qdrant.php
.. _`Similarity Search with SurrealDB (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/surrealdb.php
.. _`Similarity Search with Typesense (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/typesense.php
.. _`Azure AI Search`: https://azure.microsoft.com/products/ai-services/ai-search
.. _`Chroma`: https://www.trychroma.com/
.. _`MariaDB`: https://mariadb.org/projects/mariadb-vector/
.. _`Pinecone`: https://www.pinecone.io/
.. _`Postgres`: https://www.postgresql.org/about/news/pgvector-070-released-2852/
.. _`Meilisearch`: https://www.meilisearch.com/
.. _`Milvus`: https://milvus.io/
.. _`MongoDB Atlas`: https://www.mongodb.com/atlas
.. _`SurrealDB`: https://surrealdb.com/
.. _`InMemory`: https://www.php.net/manual/en/language.types.array.php
.. _`Qdrant`: https://qdrant.tech/
.. _`Neo4j`: https://neo4j.com/
.. _`Typesense`: https://typesense.org/
.. _`GitHub`: https://github.com/symfony/ai/issues/16
.. _`Symfony Cache`: https://symfony.com/doc/current/components/cache.html

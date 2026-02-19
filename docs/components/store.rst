Symfony AI - Store Component
============================

The Store component provides a low-level abstraction for storing and retrieving documents in a vector store.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-store

Purpose
-------

A typical use-case in agentic applications is a dynamic context-extension with similar and useful information, for so
called `Retrieval Augmented Generation`_ (RAG). The Store component implements low-level interfaces, that can be
implemented by different concrete and vendor-specific implementations, so called bridges.
On top of those bridges, the Store component provides higher level features to populate and query those stores with and
for documents.

Indexing
--------

One higher level feature is the :class:`Symfony\\AI\\Store\\Indexer`. The purpose of this service is to populate a store with documents.
Therefore it accepts one or multiple :class:`Symfony\\AI\\Store\\Document\\TextDocument` objects, converts them into embeddings and stores them in the
used vector store::

    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\AI\Store\Indexer;

    $indexer = new Indexer($platform, $model, $store);
    $document = new TextDocument('This is a sample document.');
    $indexer->index($document);

You can find more advanced usage in combination with an Agent using the store for RAG in the examples folder.

Retrieving
----------

The opposite of indexing is retrieving. The :class:`Symfony\\AI\\Store\\Retriever` is a higher level feature that allows you to
search for documents in a store based on a query string. It vectorizes the query and retrieves similar documents from the store::

    use Symfony\AI\Store\Retriever;

    $retriever = new Retriever($store, $vectorizer);
    $documents = $retriever->retrieve('What is the capital of France?');

    foreach ($documents as $document) {
        echo $document->metadata->get('source');
    }

The retriever accepts optional parameters to customize the retrieval:

* ``$options``: An array of options to pass to the underlying store query (e.g., limit, filters)

Example Usage
~~~~~~~~~~~~~

* `Basic Retriever Example`_

Similarity Search Examples
~~~~~~~~~~~~~~~~~~~~~~~~~~

* `Similarity Search with Cloudflare (RAG)`_
* `Similarity Search with Manticore Search (RAG)`_
* `Similarity Search with MariaDB (RAG)`_
* `Similarity Search with Meilisearch (RAG)`_
* `Similarity Search with memory storage (RAG)`_
* `Similarity Search with Milvus (RAG)`_
* `Similarity Search with MongoDB (RAG)`_
* `Similarity Search with Neo4j (RAG)`_
* `Similarity Search with OpenSearch (RAG)`_
* `Similarity Search with Pinecone (RAG)`_
* `Similarity Search with Qdrant (RAG)`_
* `Similarity Search with SurrealDB (RAG)`_
* `Similarity Search with Symfony Cache (RAG)`_
* `Similarity Search with Typesense (RAG)`_
* `Similarity Search with Weaviate (RAG)`_
* `Similarity Search with Supabase (RAG)`_

.. note::

    Both ``InMemory`` and ``PSR-6 cache`` vector stores will load all the data into the
    memory of the PHP process. They can be used only the amount of data fits in the
    PHP memory limit, typically for testing.

Supported Stores
----------------

* :doc:`store/azure-search` – Azure AI Search
* :doc:`store/chromadb` – ChromaDB (requires ``codewithkyrian/chromadb-php``)
* :doc:`store/clickhouse` – ClickHouse
* :doc:`store/cloudflare` – Cloudflare Vectorize
* :doc:`store/elasticsearch` – Elasticsearch
* :doc:`store/local` – InMemory & Symfony Cache (for development and testing)
* :doc:`store/manticoresearch` – ManticoreSearch
* :doc:`store/mariadb` – MariaDB (requires ``ext-pdo``, MariaDB 11.7+)
* :doc:`store/meilisearch` – Meilisearch
* :doc:`store/milvus` – Milvus
* :doc:`store/mongodb` – MongoDB Atlas (requires ``mongodb/mongodb``)
* :doc:`store/neo4j` – Neo4j
* :doc:`store/opensearch` – OpenSearch
* :doc:`store/pinecone` – Pinecone (requires ``probots-io/pinecone-php``)
* :doc:`store/postgres` – Postgres with pgvector (requires ``ext-pdo``)
* :doc:`store/qdrant` – Qdrant
* :doc:`store/redis` – Redis Stack (requires ``ext-redis``)
* :doc:`store/supabase` – Supabase (requires manual database setup)
* :doc:`store/surrealdb` – SurrealDB
* :doc:`store/typesense` – Typesense
* :doc:`store/weaviate` – Weaviate

Document Loader
---------------

Creating and/or loading documents is a critical part of any RAG-based system, as it provides the foundation for the system to understand and respond to queries.
Document loaders are responsible for fetching and preparing documents for indexing and retrieval.

To help loading documents and integrate them into your RAG system, you can use the provided document loaders or create your own custom loaders to suit your specific needs:

* :class:`Symfony\\AI\\Store\\Document\\Loader\\InMemoryLoader`
* :class:`Symfony\\AI\\Store\\Document\\Loader\\MarkdownLoader`
* :class:`Symfony\\AI\\Store\\Document\\Loader\\RssFeedLoader`
* :class:`Symfony\\AI\\Store\\Document\\Loader\\TextFileLoader`

Create a Custom Loader
----------------------

The main extension points of the Store component for document loaders is the :class:`Symfony\\AI\\Store\\Document\\LoaderInterface`,
that defines the method to load a document from a source. This leads to a loader implementing one method::

    use Symfony\AI\Store\Document\LoaderInterface;
    use Symfony\AI\Store\Document\Metadata;
    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\Component\Uid\Uuid;

    class MyDocumentLoader implements LoaderInterface
    {
        public function load(?string $source = null, array $options = []): iterable
        {
            $content = ...

            yield new TextDocument(Uuid::v7()->toRfc4122(), $content, new Metadata($metadata));
        }
    }

Commands
--------

While using the ``Store`` component in your Symfony application along with the ``AiBundle``,
you can use the ``bin/console ai:store:setup`` command to initialize the store and ``bin/console ai:store:drop`` to clean up the store:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        # ...

        store:
            chromadb:
                symfonycon:
                    collection: 'symfony_blog'

.. code-block:: terminal

    $ php bin/console ai:store:setup symfonycon
    $ php bin/console ai:store:drop symfonycon


Implementing a Bridge
---------------------

The main extension points of the Store component is the :class:`Symfony\\AI\\Store\\StoreInterface`, that defines the methods
for adding, removing and querying vectorized documents in the store.

This leads to a store implementing the following methods::

    use Symfony\AI\Store\Document\VectorDocument;
    use Symfony\AI\Store\Query\QueryInterface;
    use Symfony\AI\Store\StoreInterface;

    class MyStore implements StoreInterface
    {
        public function add(VectorDocument|array $documents): void
        {
            // Implementation to add a document to the store
        }

        public function remove(string|array $ids, array $options = []): void
        {
            // Implementation to remove documents from the store
        }

        public function query(QueryInterface $query, array $options = []): iterable
        {
            // Implementation to query the store for documents
            return $documents;
        }

        public function supports(string $queryClass): bool
        {
            // Return true if the given query class is supported
            return false;
        }
    }

Managing a store
----------------

Some vector store might requires to create table, indexes and so on before storing vectors,
the :class:`Symfony\\AI\\Store\\ManagedStoreInterface` defines the methods to setup and drop the store.

This leads to a store implementing two methods::

    use Symfony\AI\Store\ManagedStoreInterface;
    use Symfony\AI\Store\StoreInterface;

    class MyCustomStore implements ManagedStoreInterface, StoreInterface
    {
        # ...

        public function setup(array $options = []): void
        {
            // Implementation to create the store
        }

        public function drop(array $options = []): void
        {
            // Implementation to drop the store (and related vectors)
        }
    }

.. toctree::
    :maxdepth: 1
    :hidden:

    store/local
    store/azure-search
    store/chromadb
    store/clickhouse
    store/cloudflare
    store/elasticsearch
    store/manticoresearch
    store/mariadb
    store/meilisearch
    store/milvus
    store/mongodb
    store/neo4j
    store/opensearch
    store/pinecone
    store/postgres
    store/qdrant
    store/redis
    store/supabase
    store/surrealdb
    store/typesense
    store/weaviate

.. _`Retrieval Augmented Generation`: https://en.wikipedia.org/wiki/Retrieval-augmented_generation
.. _`Basic Retriever Example`: https://github.com/symfony/ai/blob/main/examples/retriever/basic.php
.. _`Similarity Search with Cloudflare (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/cloudflare.php
.. _`Similarity Search with Manticore Search (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/manticore.php
.. _`Similarity Search with MariaDB (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/mariadb-gemini.php
.. _`Similarity Search with Meilisearch (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/meilisearch.php
.. _`Similarity Search with memory storage (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/in-memory.php
.. _`Similarity Search with Milvus (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/milvus.php
.. _`Similarity Search with MongoDB (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/mongodb.php
.. _`Similarity Search with Neo4j (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/neo4j.php
.. _`Similarity Search with OpenSearch (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/opensearch.php
.. _`Similarity Search with Pinecone (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/pinecone.php
.. _`Similarity Search with Symfony Cache (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/cache.php
.. _`Similarity Search with Qdrant (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/qdrant.php
.. _`Similarity Search with SurrealDB (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/surrealdb.php
.. _`Similarity Search with Typesense (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/typesense.php
.. _`Similarity Search with Supabase (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/supabase.php
.. _`Similarity Search with Weaviate (RAG)`: https://github.com/symfony/ai/blob/main/examples/rag/weaviate.php
.. _`Symfony Cache`: https://symfony.com/doc/current/components/cache.html

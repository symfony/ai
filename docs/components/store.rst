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

    $retriever = new Retriever($vectorizer, $store);
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

* `Azure AI Search`_
* `Chroma`_ (requires ``codewithkyrian/chromadb-php`` as additional dependency)
* `Cloudflare`_
* `InMemory`_
* `Manticore Search`_
* `MariaDB`_ (requires ``ext-pdo``)
* `Meilisearch`_
* `Milvus`_
* `MongoDB Atlas`_ (requires ``mongodb/mongodb`` as additional dependency)
* `Neo4j`_
* `OpenSearch`_
* `Pinecone`_ (requires ``probots-io/pinecone-php`` as additional dependency)
* `Postgres`_ (requires ``ext-pdo``)
* `Qdrant`_
* `Supabase`_ (requires manual database setup)
* `SurrealDB`_
* `Symfony Cache`_ (requires ``symfony/cache`` as additional dependency)
* `Typesense`_
* `Weaviate`_

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
for adding vectorized documents to the store, and querying the store for documents with a vector.

This leads to a store implementing two methods::

    use Symfony\AI\Platform\Vector\Vector;
    use Symfony\AI\Store\Document\VectorDocument;
    use Symfony\AI\Store\StoreInterface;

    class MyStore implements StoreInterface
    {
        public function add(VectorDocument ...$documents): void
        {
            // Implementation to add a document to the store
        }

        public function query(Vector $vector, array $options = []): iterable
        {
            // Implementation to query the store for documents
            return $documents;
        }
    }

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
.. _`Azure AI Search`: https://azure.microsoft.com/products/ai-services/ai-search
.. _`Chroma`: https://www.trychroma.com/
.. _`Cloudflare`: https://developers.cloudflare.com/vectorize/
.. _`Manticore Search`: https://manticoresearch.com/
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
.. _`OpenSearch`: https://opensearch.org/
.. _`Typesense`: https://typesense.org/
.. _`Symfony Cache`: https://symfony.com/doc/current/components/cache.html
.. _`Weaviate`: https://weaviate.io/
.. _`Supabase`: https://https://supabase.com/

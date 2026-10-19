Doctrine Store
==============

Other stores keep vectors in a storage of their own, so you have to load the matching entities
after a query. The Doctrine store keeps each vector in a column of the entity's own table. A query
returns the entities directly.

The store supports PostgreSQL with the `pgvector`_ extension and SQLite with the `sqlite-vec`_
extension.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-doctrine-store

Register the ``vector`` type, and map the ``vector`` database type to it. Without the mapping,
Doctrine can't read the column when it compares the database with your entities:

.. code-block:: yaml

    # config/packages/doctrine.yaml
    doctrine:
        dbal:
            types:
                vector: Symfony\AI\Store\Bridge\Doctrine\Type\VectorType
            mapping_types:
                vector: vector

On PostgreSQL, the ``vector`` extension must exist before Doctrine creates a table with a vector
column. Add it to a migration that runs before the table is created:

.. code-block:: sql

    CREATE EXTENSION IF NOT EXISTS vector;

Mapping the Entity
------------------

Add a nullable ``vector`` field and an index on it. Implement
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\IndexableEntityInterface` to define the text that is
embedded::

    use Doctrine\ORM\Mapping as ORM;
    use Symfony\AI\Platform\Vector\VectorInterface;
    use Symfony\AI\Store\Bridge\Doctrine\IndexableEntityInterface;

    #[ORM\Entity]
    #[ORM\Index(name: 'article_embedding_idx', columns: ['embedding'])]
    class Article implements IndexableEntityInterface
    {
        // ...

        #[ORM\Column(type: 'vector', nullable: true)]
        private ?VectorInterface $embedding = null;

        public function getIndexableContent(): string
        {
            return $this->title."\n\n".$this->body;
        }
    }

The field has no size. PostgreSQL doesn't report the size of a vector column, so a size in the
mapping would make Doctrine change the column on every schema update.

Create the table with your usual tools (e.g. Doctrine migrations). Then call ``setup()`` to give
the column its size and to create a vector index::

    use Symfony\AI\Store\Bridge\Doctrine\Distance;
    use Symfony\AI\Store\Bridge\Doctrine\Store;

    $store = new Store($entityManager, Article::class, 'embedding', Distance::Cosine);

    // 1536 by default
    $store->setup(['dimensions' => 1536]);

On PostgreSQL, ``setup()`` replaces the index declared on the entity with an HNSW index that uses
the operator class of the distance. On SQLite, ``setup()`` does nothing.

``drop()`` removes the vector index and clears all vectors. The column and the entities stay.

Indexing Entities
-----------------

Use :class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityLoader` with a
:class:`Symfony\\AI\\Store\\Indexer\\SourceIndexer` to embed all entities of a class::

    use Symfony\AI\Store\Bridge\Doctrine\EntityLoader;
    use Symfony\AI\Store\Document\Vectorizer;
    use Symfony\AI\Store\Indexer\DocumentProcessor;
    use Symfony\AI\Store\Indexer\SourceIndexer;

    $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
    $indexer = new SourceIndexer(new EntityLoader($entityManager), new DocumentProcessor($vectorizer, $store));

    $indexer->index(Article::class);

The loader reads 100 entities per query and clears the entity manager after each page. All entities
managed by this entity manager are detached at that point. Use the ``batch_size`` and
``clear_batches`` options to change this, and ``criteria`` to index only some entities::

    $indexer->index(Article::class, ['criteria' => ['published' => true], 'batch_size' => 50]);

To build the text without the interface, pass a closure as ``contentBuilder`` to the loader.

The store writes the vectors with DBAL. Entities that are already loaded keep their old vector
until you refresh them.

Querying Entities
-----------------

A query returns :class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityVectorDocument` objects. Each one
contains the entity and its distance to the query vector::

    use Symfony\AI\Store\Query\VectorQuery;

    $query = new VectorQuery($vectorizer->vectorize($article->getIndexableContent()));

    $documents = $store->query($query, [
        // 5 by default
        'limit' => 5,
        // a plain SQL condition on the columns of the entity's table
        'where' => 'id != :self',
        'params' => ['self' => $article->getId()],
    ]);

    foreach ($documents as $document) {
        echo $document->getEntity()->getTitle();
    }

This example finds articles that are similar to ``$article``, without the article itself. Pass
``maxScore`` to skip entities whose distance is larger than the given value.

Bundle Configuration
--------------------

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            doctrine:
                articles:
                    entity: 'App\Entity\Article'
                    # 'embedding' by default
                    vector_field: 'embedding'
                    # cosine (default), euclidean or inner_product
                    distance: 'cosine'
                    # 'doctrine.orm.entity_manager' by default
                    entity_manager: 'doctrine.orm.entity_manager'
                    # '<table>_<column>_idx' by default
                    index_name: 'article_embedding_idx'
                    setup_options:
                        dimensions: 1536

Run ``bin/console ai:store:setup ai.store.doctrine.articles`` after the migrations to size the
column and to create the index.

Testing on SQLite
-----------------

On SQLite, the store keeps vectors as JSON text in a ``TEXT`` column. Your application can create
the schema and write vectors on a plain SQLite database, for example in a test suite. Only queries
need the sqlite-vec extension. Inner product distance isn't available on SQLite.

.. note::

    For other databases, implement
    :class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\VectorPlatform\\VectorPlatformInterface` and pass
    it to the store as ``vectorPlatform``.

.. _`pgvector`: https://github.com/pgvector/pgvector
.. _`sqlite-vec`: https://github.com/asg017/sqlite-vec

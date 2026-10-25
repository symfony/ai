Doctrine Store
==============

Other stores keep vectors in a storage of their own, so you have to load the matching entities
after a query. The Doctrine store keeps each vector in a field of the entity itself. Doctrine
writes it with the rest of the entity, and a query returns the entities.

The store supports PostgreSQL with the `pgvector`_ extension, MariaDB 11.7 or newer and SQLite
with the `sqlite-vec`_ extension. MySQL is not supported, because MySQL Community has no function
to compare vectors. If your database has no vector support, you can mirror the entities into
another store instead (see `Mirroring Entities`_).

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-doctrine-store

When you configure a ``doctrine`` store in the AI Bundle (see `Bundle Configuration`_), the bundle
registers the ``vector`` type and the ``VECTOR_DISTANCE`` DQL function with Doctrine. Without the
bundle, register both yourself:

.. code-block:: yaml

    # config/packages/doctrine.yaml
    doctrine:
        dbal:
            types:
                vector: Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType
        orm:
            dql:
                numeric_functions:
                    VECTOR_DISTANCE: Symfony\AI\Store\Bridge\Doctrine\Orm\Query\VectorDistance

Mapping the Entity
------------------

Add a nullable ``vector`` field and mark it with the ``#[Embedding]`` attribute. The ``from``
option lists the fields the embedding is built from::

    use Doctrine\ORM\Mapping as ORM;
    use Symfony\AI\Platform\Vector\VectorInterface;
    use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;

    #[ORM\Entity]
    #[ORM\Index(name: 'article_embedding_idx', columns: ['embedding'])]
    class Article
    {
        // ...

        #[ORM\Column(type: 'vector', nullable: true)]
        #[Embedding(from: ['title', 'body'])]
        private ?VectorInterface $embedding = null;
    }

By default, the values of these fields are joined, one per line. A field holding a list, for example
a JSON field, adds one line per item. Values that have no text, like a ``DateTimeImmutable``, throw
an exception.

To build the text in PHP, implement
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\IndexableEntityInterface`. Its
``getIndexableContent()`` method then returns the text to embed. Keep ``from`` in sync with the
fields this method reads, because only changes of these fields embed the entity again.

The field has no size. PostgreSQL doesn't report the size of a vector column, so a size in the
mapping would make Doctrine change the column on every schema update. Give the column its size and
an HNSW index in a migration instead:

.. code-block:: sql

    -- before the table is created
    CREATE EXTENSION IF NOT EXISTS vector;

    -- after the table is created
    ALTER TABLE article ALTER COLUMN embedding TYPE vector(1536);
    DROP INDEX article_embedding_idx;
    CREATE INDEX article_embedding_idx ON article USING hnsw (embedding vector_cosine_ops);

The HNSW index keeps the name and the column of the index declared on the entity, so Doctrine sees
no difference. Use ``vector_l2_ops`` or ``vector_ip_ops`` for the euclidean and the inner product
distance.

.. warning::

    Create the vector index only in a migration. ``doctrine:schema:update`` creates the index of
    the entity as a btree index, and inserts then fail above about 670 dimensions with
    ``index row size ... exceeds btree version 4 maximum 2704``.

The HNSW index of pgvector supports vectors with up to 2,000 dimensions. For a model with more
dimensions, like ``text-embedding-3-large`` with 3,072, request at most 2,000 dimensions from the
model, or leave out the index. Without the index, also remove the ``#[ORM\Index]`` from the
entity, otherwise ``doctrine:migrations:diff`` adds the btree index to every new migration. Pass the dimensions with the model name of the vectorizer, for
example ``text-embedding-3-large?dimensions=1536``, so the indexer and the listener use the same.

MariaDB
~~~~~~~

MariaDB needs the size of the column, and a vector index needs the column to be ``NOT NULL``.
Doctrine can't know either, so declare both with a ``columnDefinition``. Doctrine ignores this
option when it compares the schema, but it also ignores ``nullable: false`` then, so set both::

    #[ORM\Table(name: 'article')]
    #[ORM\Index(name: 'article_embedding_idx', columns: ['embedding'])]
    class Article
    {
        #[ORM\Column(type: 'vector', nullable: false, columnDefinition: 'VECTOR(1536) NOT NULL')]
        #[Embedding(from: ['title', 'body'])]
        private ?VectorInterface $embedding = null;
    }

``doctrine:migrations:diff`` generates the index of the entity as a btree index, and MariaDB
rejects it for a vector column of 1536 dimensions. Replace it in the generated migration with a
vector index. It keeps the name and the column of the index declared on the entity, so Doctrine
sees no difference:

.. code-block:: diff

     CREATE TABLE article (
         id INT AUTO_INCREMENT NOT NULL,
         embedding VECTOR(1536) NOT NULL,
    -    INDEX article_embedding_idx (embedding),
    +    VECTOR INDEX article_embedding_idx (embedding) DISTANCE=cosine,
         PRIMARY KEY(id)
     )

When the column is added to an existing table, the migration has a
``CREATE INDEX article_embedding_idx ON article (embedding)`` statement instead. Replace it with
``ALTER TABLE article ADD VECTOR INDEX article_embedding_idx (embedding) DISTANCE=cosine``.

Use ``DISTANCE=euclidean`` for the euclidean distance. MariaDB doesn't support the inner product.

.. warning::

    Create the vector index only in a migration. ``doctrine:schema:update`` creates the index of
    the entity as a btree index, and it fails from 769 dimensions on with
    ``ERROR 1071: Specified key was too long; max key length is 3072 bytes``.

The column is ``NOT NULL``, so every entity needs an embedding when it is saved. A failing model
call fails the flush, and ``remove()`` and ``clear()`` of the store can't set the vector to null
either. Without the index, declare the column nullable and remove the ``#[ORM\Index]``, then every
query scans the table.

Embedding Entities
------------------

With the AI Bundle, set the ``vectorizer`` of the store:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            doctrine:
                articles:
                    entity: 'App\Entity\Article'
                    vectorizer: 'ai.vectorizer.openai_embeddings'

The bundle then embeds new and changed articles when they are flushed. It also registers an indexer
with the name of the store, which embeds all existing articles again:

.. code-block:: terminal

    $ php bin/console ai:store:index articles

Without the bundle, or to control the embedding yourself, use the classes below.
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityEmbedder` sets the embedding field of entities.
Doctrine writes the field on the next flush, like any other field.

To embed a table that already has rows, use
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityIndexer`::

    use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
    use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
    use Symfony\AI\Store\Document\Vectorizer;

    $embedder = new EntityEmbedder(new Vectorizer($platform, 'text-embedding-3-small'));
    $indexer = new EntityIndexer($entityManager, $embedder);

    $indexer->index(Article::class, [
        // embeds only entities without a vector, so you can run it again after an error
        'only_missing' => true,
        // 100 by default
        'batch_size' => 100,
    ]);

The indexer embeds each batch with one call to the model and flushes it. Then it clears the entity
manager, so all entities managed by it are detached. You can also pass entities to ``index()``,
if the entity manager manages them.

To embed entities when they are created or changed, register
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EmbeddingListener` as a Doctrine listener of the
``onFlush`` event:

.. code-block:: yaml

    # config/services.yaml
    services:
        Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder:
            arguments: ['@ai.vectorizer.openai_embeddings']

        Symfony\AI\Store\Bridge\Doctrine\EmbeddingListener:
            tags:
                - { name: doctrine.event_listener, event: onFlush }

The listener embeds new entities. It embeds an existing entity again when one of the ``from``
fields changes. If the vector is already set in the same flush, for example by the indexer, the
listener keeps it. The listener calls the model during the flush, so a slow model call also slows
down the flush.

An entity that another ``onFlush`` listener persists is only embedded if that listener runs
before the embedding listener. Give the embedding listener a lower ``priority``, or run the indexer
with ``only_missing`` for these entities.

Some errors of the model call don't stop the flush: rate limits, server and network errors, a text
that is too long for the model and a text the content filter rejects. The flush then saves the
entities without embedding, and the listener logs a warning. Run the indexer with ``only_missing``
to embed these entities later. Other errors, like an invalid API key, fail the flush.

Querying Entities
-----------------

:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\OrmStore` implements the store interface. Use it with
a :class:`Symfony\\AI\\Store\\Retriever` to search entities by a text::

    use Symfony\AI\Store\Bridge\Doctrine\Dbal\Metric;
    use Symfony\AI\Store\Bridge\Doctrine\OrmStore;
    use Symfony\AI\Store\Retriever;

    // cosine by default
    $store = new OrmStore($entityManager, Article::class, Metric::Cosine);
    $retriever = new Retriever($store, $vectorizer);

    $documents = $retriever->retrieve('articles about cats', [
        // 5 by default
        'limit' => 5,
        // skips entities whose distance is larger
        'maxScore' => 0.5,
    ]);

    foreach ($documents as $document) {
        echo $document->getEntity()->getTitle();
    }

Each result is an :class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityVectorDocument` with the
entity and its distance to the query. On PostgreSQL, the inner product distance is the negative
inner product, so ``maxScore`` needs a negative value with it.

To filter or join, use ``VECTOR_DISTANCE`` in your own DQL. This query finds the articles that
are most similar to ``$article``, without the article itself. On SQLite, sqlite-vec fails on a
``NULL`` vector, so the query skips articles without embedding::

    use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;

    $similarArticles = $entityManager->createQuery(
        'SELECT a FROM App\Entity\Article a
            WHERE a.id != :id AND a.embedding IS NOT NULL
            ORDER BY VECTOR_DISTANCE(a.embedding, :vector) ASC'
    )
        ->setParameter('id', $article->getId())
        ->setParameter('vector', $article->getEmbedding(), VectorType::NAME)
        ->setMaxResults(5)
        ->getResult();

The third argument of ``VECTOR_DISTANCE`` selects the metric: ``'cosine'`` (default),
``'euclidean'`` or ``'inner_product'``.

On SQLite, PDO binds a float parameter as text, and SQLite treats any number as smaller than a
text. A condition like ``VECTOR_DISTANCE(a.embedding, :vector) < :maxDistance`` then matches every
row. Write ``< :maxDistance + 0.0`` to compare numbers.

Writing Vectors
---------------

The store keeps the vectors in your entities, so its write methods work differently from the
other stores. They write vectors only to existing entities, and never create or delete entities:

``add()``
    Sets the vector on the entity with the id of the document. If there is no such entity, it
    throws an ``InvalidArgumentException``. The metadata of the document is not stored, because
    the text of a result comes from the ``from`` fields of the entity.

``remove()``
    Sets the vector of the entities with the given ids to ``null``. The entities stay in the
    database. If there is no entity with one of the ids, it throws an ``InvalidArgumentException``.

``clear()``
    Sets all vectors to ``null`` with a bulk update. Entities that are already loaded keep their
    vector until you refresh them.

``count()``
    Returns the number of entities that have a vector.

.. warning::

    ``add()`` and ``remove()`` flush the entity manager. This also writes all other changes that
    are pending in it.

Fill the store with the entity indexer or the embedding listener. The
:class:`Symfony\\AI\\Store\\Indexer\\SourceIndexer` and
:class:`Symfony\\AI\\Store\\Indexer\\DocumentIndexer` don't work with this store. They add new
documents, and ``add()`` throws an exception for a document without an entity.

Mirroring Entities
------------------

If your database has no vector support, mirror the entities into another store instead. Put
``#[Embedding]`` on the class, without a vector field. The ``from`` option works as before::

    use Doctrine\ORM\Mapping as ORM;
    use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;

    #[ORM\Entity]
    #[Embedding(from: ['name', 'description'])]
    class Product
    {
        // ...
    }

:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\Mirror\\MirrorListener` embeds new and changed
entities with the same
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityEmbedder`, and adds them as documents to the
store. It writes after the flush, because generated ids exist only then. It removes the document
of a removed entity. Register it for the ``onFlush`` and ``postFlush`` events:

.. code-block:: yaml

    # config/services.yaml
    services:
        Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder:
            arguments: ['@ai.vectorizer.openai_embeddings']

        Symfony\AI\Store\Bridge\Doctrine\Mirror\MirrorListener:
            arguments:
                $store: '@ai.store.qdrant.catalog'
            tags:
                - { name: doctrine.event_listener, event: onFlush }
                - { name: doctrine.event_listener, event: postFlush }

The document id is a UUID built from the class and the id of the entity, so a changed entity
replaces its document. The metadata holds the class and the id. Entities need a single identifier.
Because the class name is part of the id, renaming or moving the entity class leaves the old
documents in the store. Clear the store and run the indexer again after such a change.

The database has already saved the entity when the listener writes to the store. If the model call
fails in a way described above, the listener logs a warning and the store keeps the old document.
Run the indexer to mirror these entities again. If the store itself fails, the exception reaches
your code after the flush.

``postFlush`` also runs when the flush is part of a bigger transaction, for example inside
``wrapInTransaction()`` or the ``doctrine_transaction`` middleware of Messenger. The store is then
written before the outer commit. If the transaction rolls back, the store keeps documents of
entities that were never saved and misses the removal of entities that still exist. Run the
indexer to repair this.

To read the entities back, wrap a retriever of the mirror store in
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\Mirror\\EntityRetriever`::

    use Symfony\AI\Store\Bridge\Doctrine\Mirror\EntityRetriever;
    use Symfony\AI\Store\Retriever;

    $retriever = new EntityRetriever(new Retriever($mirrorStore, $vectorizer), $entityManager);

    foreach ($retriever->retrieve('red shoes') as $document) {
        echo $document->getEntity()->getName();
    }

It returns :class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityVectorDocument` objects, like the
``OrmStore``, so ``SimilaritySearch`` works the same with both. Hits without an entity, for
example after a removal that never reached the store, are skipped. Pass a callable as the third
argument to be told about them.

To mirror a table that already has rows, pass the listener to the entity indexer as its last
argument. ``only_missing`` is not available, because there is no vector field to check::

    $indexer = new EntityIndexer($entityManager, $embedder, mirror: $mirrorListener);
    $indexer->index(Product::class);

Bundle Configuration
--------------------

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            doctrine:
                articles:
                    entity: 'App\Entity\Article'
                    # cosine (default), euclidean or inner_product
                    metric: 'cosine'
                    # 'doctrine.orm.entity_manager' by default
                    entity_manager: 'doctrine.orm.entity_manager'
                    # embeds the entities on flush and through "ai:store:index articles", null by default
                    vectorizer: 'ai.vectorizer.openai_embeddings'

To mirror the entity into another store, set ``mirror_to`` to that store. It needs a
``vectorizer`` and ``#[Embedding]`` on the entity class. The bundle then registers no ``OrmStore``.
It registers the listener, the indexer and an entity retriever instead, named like the store:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            doctrine:
                products:
                    entity: 'App\Entity\Product'
                    vectorizer: 'ai.vectorizer.openai_embeddings'
                    mirror_to: 'ai.store.qdrant.catalog'

The retriever is available as ``ai.retriever.products``. A ``retriever`` or ``indexer`` of the same
name is not allowed.

Testing on SQLite
-----------------

On SQLite, vectors are stored as JSON text in a ``CLOB`` column. Your application can create the
schema and embed entities on a plain SQLite database, for example in a test suite. Only queries
need the sqlite-vec extension. Inner product distance isn't available on SQLite.

.. _`pgvector`: https://github.com/pgvector/pgvector
.. _`sqlite-vec`: https://github.com/asg017/sqlite-vec

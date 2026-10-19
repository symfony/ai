Doctrine Store
==============

Stores embeddings in a column of the table a Doctrine entity already lives in, and hands back
entities when queried, for Symfony AI Store.

Every other store owns its storage: it keeps a table of ids, metadata and vectors, and a hit has to
be resolved back to the domain object it stands for. This bridge turns that around. The embedding is
just another field of an entity, written by the same schema tooling and covered by the same
transaction as the rest of the row, and a similarity query returns the entities themselves.

Supported platforms
-------------------

| Platform               | Column      | Distances                        | Vector index     |
| ---------------------- | ----------- | -------------------------------- | ---------------- |
| PostgreSQL + pgvector  | `vector(n)` | cosine, euclidean, inner product | HNSW             |
| SQLite + sqlite-vec    | `TEXT`      | cosine, euclidean                | none, exact scan |

On SQLite only the distance functions come from the extension - the vectors themselves are stored as
JSON text, so a schema carrying them can be created and written against stock SQLite. That is what
lets a test suite run on SQLite while production runs on one of the others.

Other platforms work by passing a `VectorPlatform\VectorPlatformInterface` implementation of your own
to the store.

Usage
-----

Register the DBAL type, and teach Doctrine to recognize the column when it introspects the database -
without the mapping, `doctrine:schema:validate` and `doctrine:migrations:diff` fail on the unknown
database type:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            vector: Symfony\AI\Store\Bridge\Doctrine\Type\VectorType
        mapping_types:
            vector: vector
```

Declare the vector as a field of the entity, without a size:

```php
use Doctrine\ORM\Mapping as ORM;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\IndexableEntityInterface;

#[ORM\Entity]
class Article implements IndexableEntityInterface
{
    #[ORM\Column(type: 'vector', nullable: true)]
    private ?VectorInterface $embedding = null;

    // Indexing and querying both go through this method, so both sides of a comparison
    // describe an article the same way.
    public function getIndexableContent(): string
    {
        return \sprintf("%s\n\n%s", $this->title, $this->body);
    }
}
```

The size is stated where the column is created rather than in the mapping. Doctrine decides whether
a column changed by comparing the SQL both sides declare themselves as, and no supported database
reports the size of a vector column when introspecting one - a sized declaration would make every
schema diff from then on want to re-`ALTER` a column that is already correct. Either let the store
size the column and index it:

```php
$store->setup(['dimensions' => 1536]);
```

On PostgreSQL the extension has to exist before any table carrying a vector column can be created,
which is a step earlier than the store can reach - run `CREATE EXTENSION IF NOT EXISTS vector` before
the schema tooling, or from a migration.

or write it into a migration, which is what an application keeping its schema in migrations wants:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
ALTER TABLE article ADD embedding vector(1536);
CREATE INDEX article_embedding_idx ON article USING hnsw (embedding vector_cosine_ops);
```

Declare that index on the entity as well - an index Doctrine does not know about is an index the
schema tool wants to drop:

```php
#[ORM\Index(name: 'article_embedding_idx', columns: ['embedding'])]
```

Indexing walks the table and writes a vector onto every row:

```php
use Symfony\AI\Store\Bridge\Doctrine\EntityLoader;
use Symfony\AI\Store\Bridge\Doctrine\Store;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Indexer\SourceIndexer;

$store = new Store($entityManager, Article::class, 'embedding');
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
$indexer = new SourceIndexer(new EntityLoader($entityManager), new DocumentProcessor($vectorizer, $store));

$indexer->index(Article::class);
```

Querying returns `EntityVectorDocument`s, each carrying the entity it matched and the distance to the
queried vector:

```php
use Symfony\AI\Store\Query\VectorQuery;

$query = new VectorQuery($vectorizer->vectorize($article->getIndexableContent()));

foreach ($store->query($query, ['limit' => 5, 'where' => 'id != :self', 'params' => ['self' => $article->getId()]]) as $document) {
    echo $document->getEntity()->getTitle(), ' ', $document->getScore(), \PHP_EOL;
}
```

The `where` fragment is plain SQL against the entity's own columns, which is what makes a "more like
this" query possible at all: it excludes the row the query started from, and can hide unpublished
rows the same way.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)

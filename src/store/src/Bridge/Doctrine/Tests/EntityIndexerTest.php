<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\AI\Store\Bridge\Doctrine\Mirror\MirrorListener;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\ArrayStore;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Book;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Document;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Memo;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Note;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Place;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Product;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Publication;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Report;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Summary;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Translation;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Untracked;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\StoreInterface;

final class EntityIndexerTest extends TestCase
{
    use SqliteEntityManagerTrait;

    private EntityManagerInterface $entityManager;
    private KeywordVectorizer $vectorizer;

    protected function setUp(): void
    {
        $this->entityManager = $this->createEntityManager();
        $this->vectorizer = new KeywordVectorizer();
    }

    public function testEmbedsTheWholeTableBatchByBatch()
    {
        $this->persist(new Article('Cats'), new Article('Dogs'), new Article('Fish'), new Article('Cats and dogs'), new Article('More fish'));

        $this->indexer()->index(Article::class, ['batch_size' => 2]);

        $this->assertSame([['Cats', 'Dogs'], ['Fish', 'Cats and dogs'], ['More fish']], $this->vectorizer->calls);
        $this->assertSame(0, $this->countWithoutEmbedding(Article::class));
    }

    public function testWritesTheTableOfAnEntityIdentifiedByAnAssociation()
    {
        $this->createSchema(Summary::class);
        $cats = new Article('Cats');
        $dogs = new Article('Dogs');
        $this->entityManager->persist($cats);
        $this->entityManager->persist($dogs);
        // a derived identifier needs the article to have its id first
        $this->entityManager->flush();
        $this->persist(new Summary($cats, 'About cats'), new Summary($dogs, 'About dogs'));

        (new EntityIndexer($this->entityManager, new EntityEmbedder($this->vectorizer)))->index(Summary::class, ['batch_size' => 1]);

        $connection = $this->entityManager->getConnection();
        $this->assertSame('[1.01,0.01,0.01]', $connection->fetchOne('SELECT embedding FROM summary WHERE article_id = ?', [$cats->getId()]));
        $this->assertSame('[0.01,1.01,0.01]', $connection->fetchOne('SELECT embedding FROM summary WHERE article_id = ?', [$dogs->getId()]));
    }

    public function testWritesTheTableOfTheClassDeclaringTheVector()
    {
        $this->createSchema(Publication::class, Book::class);
        $book = new Book('Cats', '978-3-16-148410-0');
        $this->persist($book);

        $this->indexer()->index(Book::class);

        $this->assertSame('[1.01,0.01,0.01]', $this->entityManager->getConnection()->fetchOne('SELECT embedding FROM publication WHERE id = ?', [$book->getId()]));
    }

    public function testWritesTheTableOfAVersionedEntityWithTheVersionInTheParentTable()
    {
        $this->createSchema(Document::class, Report::class);
        $report = new Report('Cats');
        $this->persist($report);

        $this->indexer()->index(Report::class);

        $this->assertSame('[1.01,0.01,0.01]', $this->entityManager->getConnection()->fetchOne('SELECT embedding FROM report WHERE id = ?', [$report->getId()]));
    }

    /**
     * @param class-string<Publication|Report> $entityClass
     */
    #[TestWith([Publication::class, 'publication', 'publication'])]
    #[TestWith([Report::class, 'report', 'document'])]
    public function testKeepsTheRowOfAnEntityEditedWhileVectorizing(string $entityClass, string $vectorTable, string $versionTable)
    {
        $this->createSchema(Publication::class, Book::class, Document::class, Report::class);
        $edited = new $entityClass('Cats');
        $untouched = new $entityClass('Dogs');
        $this->persist($edited, $untouched);
        $connection = $this->entityManager->getConnection();
        $this->vectorizer = new KeywordVectorizer(whileVectorizing: static function () use ($connection, $versionTable, $edited): void {
            $connection->executeStatement(\sprintf('UPDATE %s SET version = version + 1 WHERE id = ?', $versionTable), [$edited->getId()]);
        });

        $this->indexer()->index($entityClass);

        $this->assertNull($connection->fetchOne(\sprintf('SELECT embedding FROM %s WHERE id = ?', $vectorTable), [$edited->getId()]));
        $this->assertSame('[0.01,1.01,0.01]', $connection->fetchOne(\sprintf('SELECT embedding FROM %s WHERE id = ?', $vectorTable), [$untouched->getId()]));
    }

    public function testRunsNoLifecycleCallbacksOfTheWrittenEntities()
    {
        $this->createSchema(Publication::class, Book::class);
        $publication = new Publication('Cats');
        $this->persist($publication);

        $this->indexer()->index(Publication::class);

        $reloaded = $this->entityManager->find(Publication::class, $publication->getId());
        $this->assertNotNull($reloaded?->getEmbedding());
        $this->assertNull($reloaded->getChangedAt());
    }

    public function testRefreshesTheSecondLevelCache()
    {
        $this->entityManager = $this->createEntityManager(secondLevelCache: true);
        $this->createSchema(Publication::class, Book::class);
        $publication = new Publication('Cats');
        $this->persist($publication);
        $this->assertNull($this->entityManager->find(Publication::class, $publication->getId())?->getEmbedding());
        $this->assertSame([], $this->embeddedPublications());
        $this->entityManager->clear();

        $this->indexer()->index(Publication::class);

        $this->assertNotNull($this->entityManager->find(Publication::class, $publication->getId())?->getEmbedding());
        $this->entityManager->clear();
        $this->assertCount(1, $this->embeddedPublications());
    }

    public function testEmbedsOnlyWhatIsMissing()
    {
        $this->persist(new Article('Cats'), new Article('Dogs'), new Article('Fish'));
        $this->indexer()->index(Article::class);
        $this->persist(new Article('More fish'));
        $this->vectorizer->calls = [];

        $this->indexer()->index(Article::class, ['only_missing' => true, 'batch_size' => 1]);

        $this->assertSame([['More fish']], $this->vectorizer->calls);
        $this->assertSame(0, $this->countWithoutEmbedding(Article::class));
    }

    public function testEmbedsAgainWhenAskedForEverything()
    {
        $this->persist(new Article('Cats'), new Article('Dogs'));
        $this->indexer()->index(Article::class);
        $this->vectorizer->calls = [];

        $this->indexer()->index(Article::class);

        $this->assertSame([['Cats', 'Dogs']], $this->vectorizer->calls);
    }

    public function testWritesTheTableWithoutAFlush()
    {
        $listener = new class {
            /** @var list<string> */
            public array $events = [];

            public function onFlush(): void
            {
                $this->events[] = Events::onFlush;
            }

            public function preUpdate(): void
            {
                $this->events[] = Events::preUpdate;
            }
        };
        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onFlush, Events::preUpdate], $listener);
        $this->entityManager = $this->createEntityManager($eventManager);
        $this->persist(new Article('Cats'), new Article('Dogs'));
        $listener->events = [];

        $this->indexer()->index(Article::class);

        $this->assertSame([], $listener->events);
        $this->assertSame(0, $this->countWithoutEmbedding(Article::class));
    }

    public function testPagesThroughObjectIdentifiers()
    {
        $this->persist(new Note('Cats'), new Note('Dogs'), new Note('Fish'));

        $this->indexer()->index(Note::class, ['batch_size' => 2]);

        $this->assertCount(2, $this->vectorizer->calls);
        $this->assertSame(0, $this->countWithoutEmbedding(Note::class));
    }

    public function testEmbedsTheEntitiesItIsGiven()
    {
        $cats = new Article('Cats');
        $dogs = new Article('Dogs');
        $this->entityManager->persist($cats);
        $this->entityManager->persist($dogs);
        $this->entityManager->flush();

        $this->indexer()->index($cats);
        $this->indexer()->index([$dogs]);

        $this->assertSame([['Cats'], ['Dogs']], $this->vectorizer->calls);
        $this->assertSame(0, $this->countWithoutEmbedding(Article::class));
    }

    public function testRefusesAnEntityTheEntityManagerDoesNotManage()
    {
        $article = new Article('Cats');
        // persisting clears the entity manager, so the article is detached
        $this->persist($article);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" entity is not managed by the entity manager, persist it or load it before indexing it.', Article::class));

        $this->indexer()->index($article);
    }

    public function testWritesTheVectorsOfEntitiesWithExplicitChangeTracking()
    {
        $this->persist(new Memo('Cats'));
        $this->indexer()->index(Memo::class);
        $this->entityManager->clear();

        $this->assertSame(0, $this->countWithoutEmbedding(Memo::class));
    }

    public function testRefusesAnEntityWithoutEmbedding()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->indexer()->index(Untracked::class);
    }

    public function testRefusesAnEntityWithACompositeIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Entity "%s" has a composite identifier, the indexer needs a single one to walk the table.', Translation::class));

        $this->indexer()->index(Translation::class);
    }

    public function testEmbedsTheTablesOfSeveralClasses()
    {
        $this->persist(new Article('Cats'), new Note('Dogs'));

        $this->indexer()->index([Article::class, Note::class]);

        $this->assertSame(0, $this->countWithoutEmbedding(Article::class));
        $this->assertSame(0, $this->countWithoutEmbedding(Note::class));
    }

    public function testMirrorsTheTableIntoTheStore()
    {
        $this->persist(new Product('Cats'), new Product('Dogs'), new Place('Fish'));
        $store = new ArrayStore();

        $this->indexer($store)->index([Product::class, Place::class], ['batch_size' => 1]);

        $this->assertCount(3, $store);
    }

    public function testMirrorsTheEntitiesItIsGiven()
    {
        $cats = new Product('Cats');
        $this->entityManager->persist($cats);
        $this->entityManager->flush();
        $store = new ArrayStore();

        $this->indexer($store)->index($cats);

        $this->assertCount(1, $store);
    }

    public function testFailsWhenTheModelFailsWhileMirroring()
    {
        $this->vectorizer = new KeywordVectorizer(maxLength: 3);
        $this->persist(new Product('Cats'));
        $store = new ArrayStore();

        try {
            $this->indexer($store)->index(Product::class);
            $this->fail('The indexer should fail when nothing reaches the store.');
        } catch (ExceedContextSizeException) {
            $this->assertCount(0, $store);
        }
    }

    public function testRefusesAnEntityThatIsNotMirroredWhenMirroring()
    {
        $this->persist(new Article('Cats'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Entity "%s" is not mirrored', Article::class));

        $this->indexer(new ArrayStore())->index(Article::class);
    }

    public function testRefusesAMirroredEntityTheListenerDoesNotHandle()
    {
        $this->persist(new Product('Cats'), new Place('Dogs'));
        $embedder = new EntityEmbedder($this->vectorizer);
        $indexer = new EntityIndexer($this->entityManager, $embedder, mirror: new MirrorListener($embedder, new ArrayStore(), Product::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('does not handle "%s" entities', Place::class));

        try {
            $indexer->index(Place::class);
        } finally {
            $this->assertSame([], $this->vectorizer->calls);
        }
    }

    public function testRefusesToMirrorWithoutAMirrorListener()
    {
        $this->persist(new Product('Cats'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('need a mirror listener');

        $this->indexer()->index(Product::class);
    }

    public function testRefusesOnlyMissingForAMirroredEntity()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"only_missing" has no field to check');

        $this->indexer(new ArrayStore())->index(Product::class, ['only_missing' => true]);
    }

    public function testRefusesPlatformOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The entity indexer does not take "platform_options"');

        $this->indexer()->index(Article::class, ['platform_options' => ['dimensions' => 1536]]);
    }

    public function testRefusesAClassThatDoesNotExist()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->indexer()->index('App\Entity\Missing');
    }

    /**
     * @param class-string ...$entityClasses
     */
    private function createSchema(string ...$entityClasses): void
    {
        // SQLite reports a schema change for a foreign key on the primary key, so not in the shared schema
        (new SchemaTool($this->entityManager))->createSchema(array_map($this->entityManager->getClassMetadata(...), array_values($entityClasses)));
    }

    private function indexer(?StoreInterface $mirror = null): EntityIndexer
    {
        $embedder = new EntityEmbedder($this->vectorizer);

        return new EntityIndexer($this->entityManager, $embedder, mirror: null === $mirror ? null : new MirrorListener($embedder, $mirror));
    }

    /**
     * @return list<Publication>
     */
    private function embeddedPublications(): array
    {
        return $this->entityManager->createQuery(\sprintf('SELECT p FROM %s p WHERE p.embedding IS NOT NULL', Publication::class))
            ->setCacheable(true)
            ->getResult();
    }

    private function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param class-string $class
     */
    private function countWithoutEmbedding(string $class): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(e)')
            ->from($class, 'e')
            ->where('e.embedding IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

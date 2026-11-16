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

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Metric;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Bridge\Doctrine\OrmStore;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Memo;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Note;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Translation;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Untracked;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\Retriever;

final class OrmStoreTest extends TestCase
{
    use SqliteEntityManagerTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createEntityManager();
    }

    public function testAddsAVectorToTheEntityWithTheDocumentId()
    {
        [$article] = $this->persist(new Article('Cats'));

        $this->store()->add(new VectorDocument($article->getId(), new Vector([0.25, 0.5, 0.75])));

        $this->assertSame([0.25, 0.5, 0.75], $article->getEmbedding()?->getData());
        $this->assertSame('[0.25,0.5,0.75]', $this->storedVector($article));
    }

    public function testAddsTheVectorOfAnEntityDocumentToItsEntity()
    {
        [$article] = $this->persist(new Article('Cats'));

        $this->store()->add([new EntityVectorDocument($article, 'ignored', new Vector([1.0, 0.0, 0.0]))]);

        $this->assertSame('[1,0,0]', $this->storedVector($article));
    }

    public function testAddsTheVectorOfAnEntityDocumentWithADetachedEntity()
    {
        [$article] = $this->persist(new Article('Cats'));
        $this->entityManager->clear();

        $this->store()->add(new EntityVectorDocument($article, (string) $article->getId(), new Vector([1.0, 0.0, 0.0])));

        $this->assertSame('[1,0,0]', $this->storedVector($article));
    }

    public function testAddsTheVectorOfAnEntityDocumentOfAnotherClassToTheEntityWithItsId()
    {
        [$article] = $this->persist(new Article('Cats'));
        [$note] = $this->persist(new Note('Dogs'));

        $this->store()->add(new EntityVectorDocument($note, (string) $article->getId(), new Vector([1.0, 0.0, 0.0])));

        $this->assertSame('[1,0,0]', $this->storedVector($article));
        $this->assertNull($note->getEmbedding());
    }

    public function testRefusesAVectorWithoutEntity()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no "'.Article::class.'" entity with the id "42".');

        $this->store()->add(new VectorDocument(42, new Vector([1.0, 0.0, 0.0])));
    }

    public function testAddsVectorsByObjectIdentifier()
    {
        [$note] = $this->persist(new Note('Cats'));

        $this->store(Note::class)->add(new VectorDocument((string) $note->getId(), new Vector([1.0, 0.0, 0.0])));

        $this->assertSame(1, $this->store(Note::class)->count());
    }

    public function testRemovesTheVectorButKeepsTheEntity()
    {
        [$cats, $dogs] = $this->persist(new Article('Cats'), new Article('Dogs'));
        $this->embed($cats, $dogs);

        $this->store()->remove((string) $cats->getId());

        $this->assertNull($this->storedVector($cats));
        $this->assertNotNull($this->storedVector($dogs));
        $this->assertSame(2, $this->countArticles());
    }

    public function testWritesTheVectorOfAnEntityWithExplicitChangeTracking()
    {
        [$memo] = $this->persist(new Memo('Cats'));
        $connection = $this->entityManager->getConnection();

        $this->store(Memo::class)->add(new VectorDocument($memo->getId(), new Vector([1.0, 0.0, 0.0])));
        $this->assertSame('[1,0,0]', $connection->fetchOne('SELECT embedding FROM memo WHERE id = ?', [$memo->getId()]));

        $this->store(Memo::class)->remove((string) $memo->getId());
        $this->assertNull($connection->fetchOne('SELECT embedding FROM memo WHERE id = ?', [$memo->getId()]));
    }

    public function testClearsEveryVectorButKeepsTheEntities()
    {
        [$cats, $dogs] = $this->persist(new Article('Cats'), new Article('Dogs'));
        $this->embed($cats, $dogs);

        $this->store()->clear();

        $this->assertSame(0, $this->store()->count());
        $this->assertSame(2, $this->countArticles());
    }

    public function testCountsOnlyEntitiesWithAVector()
    {
        [$cats] = $this->persist(new Article('Cats'), new Article('Dogs'));

        $this->assertCount(0, $this->store());

        $this->embed($cats);

        $this->assertCount(1, $this->store());
    }

    public function testRanksEntitiesByDistance()
    {
        $this->embed(...$this->persist(new Article('Fish'), new Article('Cats'), new Article('Cats and dogs')));

        $documents = $this->store()->query(new VectorQuery((new KeywordVectorizer())->vectorFor('cat')));

        $this->assertSame(['Cats', 'Cats and dogs', 'Fish'], $this->titles($documents));
        $this->assertLessThan($documents[1]->getScore(), $documents[0]->getScore());
    }

    public function testRanksWithTheConfiguredMetric()
    {
        $this->embed(...$this->persist(new Article('Cats cats cats'), new Article('Cats')));

        $documents = $this->store(metric: Metric::Euclidean)->query(new VectorQuery((new KeywordVectorizer())->vectorFor('cat')));

        $this->assertSame(['Cats', 'Cats cats cats'], $this->titles($documents));
        $this->assertEqualsWithDelta(0.0, $documents[0]->getScore(), 0.0001);
        $this->assertEqualsWithDelta(2.0, $documents[1]->getScore(), 0.0001);
    }

    public function testLimitsTheNumberOfEntities()
    {
        $this->embed(...$this->persist(new Article('Cats'), new Article('Dogs'), new Article('Fish')));

        $this->assertCount(2, $this->store()->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])), ['limit' => 2]));
    }

    public function testLeavesOutEntitiesBeyondTheMaxScore()
    {
        $this->embed(...$this->persist(new Article('Cats'), new Article('Dogs')));

        $documents = $this->store()->query(new VectorQuery((new KeywordVectorizer())->vectorFor('cat')), ['maxScore' => 0.5]);

        $this->assertSame(['Cats'], $this->titles($documents));
    }

    public function testSkipsEntitiesWithoutVector()
    {
        [$cats] = $this->persist(new Article('Cats'), new Article('Dogs'));
        $this->embed($cats);

        $documents = $this->store()->query(new VectorQuery((new KeywordVectorizer())->vectorFor('dog')));

        $this->assertSame(['Cats'], $this->titles($documents));
    }

    public function testDescribesTheEntityByItsEmbeddedContent()
    {
        [$article] = $this->persist(new Article('Cats', 'All about cats.'));
        $this->embed($article);

        $document = $this->store()->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])))[0];

        $this->assertSame((string) $article->getId(), $document->getId());
        $this->assertSame("Cats\nAll about cats.", $document->getMetadata()->getText());
        $this->assertSame([2.01, 0.01, 0.01], $document->getVector()->getData());
    }

    public function testAnswersATextQueryThroughTheRetriever()
    {
        $this->embed(...$this->persist(new Article('Dogs'), new Article('Fish')));

        $documents = iterator_to_array((new Retriever($this->store(), new KeywordVectorizer()))->retrieve('fish'), false);

        $this->assertSame(['Fish', 'Dogs'], $this->titles($documents));
    }

    public function testOnlyAnswersVectorQueries()
    {
        $this->assertTrue($this->store()->supports(VectorQuery::class));
        $this->assertFalse($this->store()->supports(TextQuery::class));
    }

    public function testRefusesAQueryItCannotAnswer()
    {
        $this->expectException(UnsupportedQueryTypeException::class);

        $this->store()->query(new TextQuery('cats'));
    }

    public function testRefusesAnEntityWithoutEmbedding()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store(Untracked::class)->count();
    }

    public function testRefusesAnEntityWithACompositeIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Entity "%s" has a composite identifier, the store needs a single one as document id.', Translation::class));

        $this->store(Translation::class)->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])));
    }

    /**
     * @param class-string $class
     */
    private function store(string $class = Article::class, Metric $metric = Metric::Cosine): OrmStore
    {
        return new OrmStore($this->entityManager, $class, $metric);
    }

    /**
     * @template T of object
     *
     * @param T ...$entities
     *
     * @return list<T>
     */
    private function persist(object ...$entities): array
    {
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();

        return array_values($entities);
    }

    private function embed(object ...$entities): void
    {
        (new EntityIndexer($this->entityManager, new EntityEmbedder(new KeywordVectorizer())))->index($entities);
    }

    private function storedVector(Article $article): mixed
    {
        return $this->entityManager->getConnection()->fetchOne('SELECT embedding FROM article WHERE id = ?', [$article->getId()]);
    }

    private function countArticles(): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM article');
    }

    /**
     * @param iterable<mixed> $documents
     *
     * @return list<string>
     */
    private function titles(iterable $documents): array
    {
        $titles = [];
        foreach ($documents as $document) {
            $this->assertInstanceOf(EntityVectorDocument::class, $document);
            $entity = $document->getEntity();
            $this->assertInstanceOf(Article::class, $entity);
            $titles[] = $entity->getTitle();
        }

        return $titles;
    }
}

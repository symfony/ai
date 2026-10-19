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

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Bridge\Doctrine\Store;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\FirstDimensionVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Note;
use Symfony\AI\Store\Bridge\Doctrine\Type\VectorType;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Bridge\Doctrine\Types\UlidType;

final class StoreTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!Type::hasType(VectorType::NAME)) {
            Type::addType(VectorType::NAME, VectorType::class);
        }

        if (!Type::hasType(UlidType::NAME)) {
            Type::addType(UlidType::NAME, UlidType::class);
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/Fixtures'], true);

        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);

        $this->entityManager = new EntityManager($connection, $configuration);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(Article::class),
            $this->entityManager->getClassMetadata(Note::class),
        ]);
    }

    public function testWritesTheVectorOntoTheRowOfItsEntity()
    {
        $article = $this->persist(new Article('Reality TV', 'A show about people.'));

        $this->store()->add(new VectorDocument($article->getId(), new Vector([0.25, 0.5])));

        $this->assertSame('[0.25,0.5]', $this->storedVector($article));
    }

    public function testWritesAWholeBatchOfVectors()
    {
        $first = $this->persist(new Article('First'));
        $second = $this->persist(new Article('Second'));

        $this->store()->add([
            new VectorDocument($first->getId(), new Vector([1.0])),
            new VectorDocument($second->getId(), new Vector([2.0])),
        ]);

        $this->assertSame('[1]', $this->storedVector($first));
        $this->assertSame('[2]', $this->storedVector($second));
    }

    public function testRemovingAVectorLeavesTheEntityAlone()
    {
        $article = $this->persist(new Article('Reality TV'));
        $store = $this->store();
        $store->add(new VectorDocument($article->getId(), new Vector([1.0])));

        $store->remove((string) $article->getId());

        $this->assertNull($this->storedVector($article));
        $this->assertSame(1, $this->countArticles());
    }

    public function testClearingDropsEveryVectorButNoEntity()
    {
        $first = $this->persist(new Article('First'));
        $second = $this->persist(new Article('Second'));
        $store = $this->store();
        $store->add([
            new VectorDocument($first->getId(), new Vector([1.0])),
            new VectorDocument($second->getId(), new Vector([2.0])),
        ]);

        $store->clear();

        $this->assertNull($this->storedVector($first));
        $this->assertNull($this->storedVector($second));
        $this->assertSame(2, $this->countArticles());
    }

    public function testDroppingKeepsTheColumnAndTheEntities()
    {
        $article = $this->persist(new Article('Reality TV'));
        $store = $this->store();
        $store->setup();
        $store->add(new VectorDocument($article->getId(), new Vector([1.0])));

        $store->drop();

        $this->assertNull($this->storedVector($article));
        $this->assertSame(1, $this->countArticles());
    }

    public function testCountsOnlyEntitiesCarryingAVector()
    {
        $first = $this->persist(new Article('First'));
        $this->persist(new Article('Second'));
        $store = $this->store();

        $this->assertCount(0, $store);

        $store->add(new VectorDocument($first->getId(), new Vector([1.0])));

        $this->assertCount(1, $store);
        $this->assertSame(2, $this->countArticles());
    }

    public function testAddingNothingTouchesNothing()
    {
        $article = $this->persist(new Article('Reality TV'));

        $this->store()->add([]);

        $this->assertNull($this->storedVector($article));
    }

    public function testOnlyAnswersVectorQueries()
    {
        $store = $this->store();

        $this->assertTrue($store->supports(VectorQuery::class));
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testRefusesAQueryItCannotAnswer()
    {
        $this->expectException(UnsupportedQueryTypeException::class);

        $this->store()->query(new TextQuery(['reality']));
    }

    public function testRanksEntitiesByDistance()
    {
        $near = $this->persist(new Article('Near'));
        $far = $this->persist(new Article('Far'));
        $this->persist(new Article('Not indexed'));
        $store = $this->queryableStore(Article::class);
        $store->add([
            new VectorDocument($far->getId(), new Vector([5.0])),
            new VectorDocument($near->getId(), new Vector([1.5])),
        ]);

        $documents = iterator_to_array($store->query(new VectorQuery(new Vector([1.0]))), false);

        $this->assertSame([(string) $near->getId(), (string) $far->getId()], array_map(static fn (EntityVectorDocument $document): string => (string) $document->getId(), $documents));
        $this->assertSame($near, $documents[0]->getEntity());
        $this->assertSame(0.5, $documents[0]->getScore());
        $this->assertSame([5.0], $documents[1]->getVector()->getData());
    }

    public function testLeavesOutEntitiesBeyondTheMaxScore()
    {
        $near = $this->persist(new Article('Near'));
        $far = $this->persist(new Article('Far'));
        $store = $this->queryableStore(Article::class);
        $store->add([
            new VectorDocument($near->getId(), new Vector([1.5])),
            new VectorDocument($far->getId(), new Vector([5.0])),
        ]);

        $documents = iterator_to_array($store->query(new VectorQuery(new Vector([1.0])), ['maxScore' => 1.0]), false);

        $this->assertCount(1, $documents);
        $this->assertSame($near, $documents[0]->getEntity());
    }

    public function testFindsEntitiesWithABinaryIdentifier()
    {
        $note = new Note('Reality TV');
        $this->entityManager->persist($note);
        $this->entityManager->flush();
        $store = $this->queryableStore(Note::class);
        $store->add(new VectorDocument((string) $note->getId(), new Vector([1.0])));

        $documents = iterator_to_array($store->query(new VectorQuery(new Vector([1.0]))), false);

        $this->assertCount(1, $documents);
        $this->assertSame($note, $documents[0]->getEntity());
        $this->assertSame((string) $note->getId(), $documents[0]->getId());
    }

    public function testRefusesAFieldTheEntityDoesNotHave()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/has no field "nope"/');

        $this->store('nope')->clear();
    }

    private function store(string $vectorField = 'embedding'): Store
    {
        return new Store($this->entityManager, Article::class, $vectorField);
    }

    /**
     * @param class-string $entityClass
     */
    private function queryableStore(string $entityClass): Store
    {
        return new Store($this->entityManager, $entityClass, vectorPlatform: new FirstDimensionVectorPlatform());
    }

    private function persist(Article $article): Article
    {
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return $article;
    }

    private function storedVector(Article $article): ?string
    {
        $stored = $this->entityManager->getConnection()->fetchOne(
            'SELECT embedding FROM article WHERE id = ?',
            [$article->getId()],
        );

        return false === $stored ? null : $stored;
    }

    private function countArticles(): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM article');
    }
}

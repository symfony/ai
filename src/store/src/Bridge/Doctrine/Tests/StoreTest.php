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
use Symfony\AI\Store\Bridge\Doctrine\Store;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Type\VectorType;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;

/**
 * Runs against a real (SQLite) entity manager: what this store does is almost entirely a matter of
 * addressing the right table and column, which mocks would only assert back at the test.
 *
 * Similarity queries are the exception - their distance functions come from sqlite-vec, which is not
 * loaded here - so they are covered by the dialect tests instead.
 */
final class StoreTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!Type::hasType(VectorType::NAME)) {
            Type::addType(VectorType::NAME, VectorType::class);
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/Fixtures'], true);

        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);

        $this->entityManager = new EntityManager($connection, $configuration);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([$this->entityManager->getClassMetadata(Article::class)]);
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

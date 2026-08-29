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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\Distance;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Bridge\Doctrine\Store;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Type\VectorType;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\VectorQuery;

/**
 * Exercises the pgvector dialect against a real server.
 *
 * The unit tests assert the SQL this bridge generates; only a real server says whether pgvector
 * accepts it, orders by it, and gives the vectors back unchanged.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends TestCase
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

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $_SERVER['POSTGRES_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_SERVER['POSTGRES_PORT'] ?? 5432),
            'dbname' => $_SERVER['POSTGRES_DB'] ?? 'test_database',
            'user' => $_SERVER['POSTGRES_USER'] ?? 'postgres',
            'password' => $_SERVER['POSTGRES_PASSWORD'] ?? 'postgres',
        ], $configuration);

        // Doctrine only learns what a "vector" column is once it is told, and without that the
        // schema manager refuses to introspect a table carrying one.
        $connection->getDatabasePlatform()->registerDoctrineTypeMapping('vector', VectorType::NAME);

        // The extension has to exist before any table carrying a vector column can be created,
        // which is a step earlier than `Store::setup()` - the schema tool gets there first.
        $connection->executeStatement('CREATE EXTENSION IF NOT EXISTS vector');

        $this->entityManager = new EntityManager($connection, $configuration);

        $metadata = [$this->entityManager->getClassMetadata(Article::class)];
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testSetupSizesTheColumnAndIndexesIt(): void
    {
        $this->store()->setup(['dimensions' => 3]);

        $connection = $this->entityManager->getConnection();

        $this->assertSame('vector(3)', $connection->fetchOne(
            "SELECT format_type(a.atttypid, a.atttypmod) FROM pg_attribute a
             WHERE a.attrelid = 'article'::regclass AND a.attname = 'embedding'",
        ));
        $this->assertSame('article_embedding_idx', $connection->fetchOne(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'article' AND indexname = 'article_embedding_idx'",
        ));
    }

    public function testRanksEntitiesByDistanceToTheQueriedVector(): void
    {
        $store = $this->store();
        $store->setup(['dimensions' => 3]);

        $near = $this->persist(new Article('Near'));
        $far = $this->persist(new Article('Far'));
        $middle = $this->persist(new Article('Middle'));

        $store->add([
            new VectorDocument($near->getId(), new Vector([1.0, 0.0, 0.0])),
            new VectorDocument($far->getId(), new Vector([0.0, 0.0, 1.0])),
            new VectorDocument($middle->getId(), new Vector([0.7, 0.7, 0.0])),
        ]);

        $documents = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0]))), false);

        $this->assertCount(3, $documents);
        $this->assertContainsOnlyInstancesOf(EntityVectorDocument::class, $documents);
        $this->assertSame(['Near', 'Middle', 'Far'], array_map($this->titleOf(...), $documents));
        $this->assertEqualsWithDelta(0.0, $documents[0]->getScore(), 0.0001);
        $this->assertGreaterThan($documents[0]->getScore(), $documents[1]->getScore());
    }

    public function testReadsBackTheVectorItStored(): void
    {
        $store = $this->store();
        $store->setup(['dimensions' => 3]);
        $article = $this->persist(new Article('Reality TV'));

        $store->add(new VectorDocument($article->getId(), new Vector([0.25, -0.5, 0.75])));
        $documents = iterator_to_array($store->query(new VectorQuery(new Vector([0.25, -0.5, 0.75]))), false);

        $this->assertSame([0.25, -0.5, 0.75], $documents[0]->getVector()->getData());
    }

    public function testNarrowsResultsWithAWhereClause(): void
    {
        $store = $this->store();
        $store->setup(['dimensions' => 3]);
        $self = $this->persist(new Article('Self'));
        $other = $this->persist(new Article('Other'));

        $store->add([
            new VectorDocument($self->getId(), new Vector([1.0, 0.0, 0.0])),
            new VectorDocument($other->getId(), new Vector([0.9, 0.1, 0.0])),
        ]);

        // The "more like this" shape: everything close to me, except me.
        $documents = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])), [
            'where' => 'id != :self',
            'params' => ['self' => $self->getId()],
        ]), false);

        $this->assertCount(1, $documents);
        $this->assertSame('Other', $this->titleOf($documents[0]));
    }

    public function testEntitiesWithoutAVectorAreNotCandidates(): void
    {
        $store = $this->store();
        $store->setup(['dimensions' => 3]);
        $indexed = $this->persist(new Article('Indexed'));
        $this->persist(new Article('Not indexed'));

        $store->add(new VectorDocument($indexed->getId(), new Vector([1.0, 0.0, 0.0])));
        $documents = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0]))), false);

        $this->assertCount(1, $documents);
        $this->assertSame('Indexed', $this->titleOf($documents[0]));
    }

    public function testClearingKeepsTheEntities(): void
    {
        $store = $this->store();
        $store->setup(['dimensions' => 3]);
        $article = $this->persist(new Article('Reality TV'));
        $store->add(new VectorDocument($article->getId(), new Vector([1.0, 0.0, 0.0])));

        $store->clear();

        $this->assertSame([], iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0]))), false));
        $this->assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM article'));
    }

    public function testDropRemovesTheColumnAndItsIndex(): void
    {
        $store = $this->store();
        $store->setup(['dimensions' => 3]);

        $store->drop();

        $connection = $this->entityManager->getConnection();
        $this->assertFalse($connection->fetchOne(
            "SELECT true FROM pg_attribute WHERE attrelid = 'article'::regclass AND attname = 'embedding' AND NOT attisdropped",
        ));
    }

    private function titleOf(EntityVectorDocument $document): string
    {
        $entity = $document->getEntity();
        $this->assertInstanceOf(Article::class, $entity);

        return $entity->getTitle();
    }

    private function store(): Store
    {
        return new Store($this->entityManager, Article::class, 'embedding', Distance::Cosine);
    }

    private function persist(Article $article): Article
    {
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return $article;
    }
}

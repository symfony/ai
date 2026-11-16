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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Metric;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;
use Symfony\AI\Store\Bridge\Doctrine\EmbeddingListener;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Bridge\Doctrine\OrmStore;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\SizedArticle;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\VectorQuery;

#[Group('integration')]
final class MariaDbIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private KeywordVectorizer $vectorizer;

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
            'driver' => 'pdo_mysql',
            'host' => $_SERVER['MARIADB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_SERVER['MARIADB_PORT'] ?? 3306),
            'dbname' => $_SERVER['MARIADB_DATABASE'] ?? 'test_database',
            'user' => $_SERVER['MARIADB_USER'] ?? 'root',
            'password' => $_SERVER['MARIADB_PASSWORD'] ?? 'root',
        ], $configuration);

        $this->vectorizer = new KeywordVectorizer();
        $eventManager = new EventManager();
        $eventManager->addEventListener(Events::onFlush, new EmbeddingListener(new EntityEmbedder($this->vectorizer)));

        $this->entityManager = new EntityManager($connection, $configuration, $eventManager);

        $metadata = [$this->entityManager->getClassMetadata(SizedArticle::class)];
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $connection->executeStatement('DROP INDEX sized_article_embedding_idx ON sized_article');
        $connection->executeStatement('ALTER TABLE sized_article ADD VECTOR INDEX sized_article_embedding_idx (embedding) DISTANCE=cosine');
    }

    public function testTheMigrationLeavesNothingForTheSchemaToolToChange()
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $statements = $schemaTool->getUpdateSchemaSql([$this->entityManager->getClassMetadata(SizedArticle::class)]);

        $this->assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'sized_article'))));
    }

    public function testEmbedsOnFlushAndRanksEntitiesByDistance()
    {
        $this->persist(new SizedArticle('Fish'), new SizedArticle('Cats'), new SizedArticle('Cats and dogs'));

        $documents = (new OrmStore($this->entityManager, SizedArticle::class))->query(new VectorQuery($this->vectorizer->vectorFor('cat')));

        $this->assertSame(['Cats', 'Cats and dogs', 'Fish'], array_map($this->titleOf(...), $documents));
        $this->assertEqualsWithDelta(0.0, $documents[0]->getScore(), 0.0001);
    }

    public function testRanksByEuclideanDistance()
    {
        $this->persist(new SizedArticle('Fish'), new SizedArticle('Cats'));

        $documents = (new OrmStore($this->entityManager, SizedArticle::class, Metric::Euclidean))->query(new VectorQuery($this->vectorizer->vectorFor('cat')));

        $this->assertSame(['Cats', 'Fish'], array_map($this->titleOf(...), $documents));
    }

    public function testReadsBackTheStoredVector()
    {
        $article = $this->persist(new SizedArticle('Cats'))[0];
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(SizedArticle::class, $article->getId());

        $this->assertSame([1.01, 0.01, 0.01], $reloaded?->getEmbedding()?->getData());
    }

    public function testRefusesTheInnerProduct()
    {
        $this->persist(new SizedArticle('Cats'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MariaDB does not implement the inner product metric');

        (new OrmStore($this->entityManager, SizedArticle::class, Metric::InnerProduct))->query(new VectorQuery($this->vectorizer->vectorFor('cat')));
    }

    private function titleOf(mixed $document): string
    {
        $this->assertInstanceOf(EntityVectorDocument::class, $document);
        $entity = $document->getEntity();
        $this->assertInstanceOf(SizedArticle::class, $entity);

        return $entity->getTitle();
    }

    /**
     * @return list<SizedArticle>
     */
    private function persist(SizedArticle ...$articles): array
    {
        foreach ($articles as $article) {
            $this->entityManager->persist($article);
        }

        $this->entityManager->flush();

        return array_values($articles);
    }
}

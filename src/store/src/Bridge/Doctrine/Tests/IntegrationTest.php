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
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Bridge\Doctrine\OrmStore;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\Retriever;

#[Group('integration')]
final class IntegrationTest extends TestCase
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
            'driver' => 'pdo_pgsql',
            'host' => $_SERVER['POSTGRES_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_SERVER['POSTGRES_PORT'] ?? 5432),
            'dbname' => $_SERVER['POSTGRES_DB'] ?? 'test_database',
            'user' => $_SERVER['POSTGRES_USER'] ?? 'postgres',
            'password' => $_SERVER['POSTGRES_PASSWORD'] ?? 'postgres',
        ], $configuration);

        // The schema tool creates the vector column, so the extension comes first
        $connection->executeStatement('CREATE EXTENSION IF NOT EXISTS vector');

        $this->vectorizer = new KeywordVectorizer();
        $eventManager = new EventManager();
        $eventManager->addEventListener(Events::onFlush, new EmbeddingListener(new EntityEmbedder($this->vectorizer)));

        $this->entityManager = new EntityManager($connection, $configuration, $eventManager);

        $metadata = [$this->entityManager->getClassMetadata(Article::class)];
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // What a migration adds on top of the mapping: the vector size and an HNSW index in place of the btree one
        $connection->executeStatement('ALTER TABLE article ALTER COLUMN embedding TYPE vector(3)');
        $connection->executeStatement('DROP INDEX article_embedding_idx');
        $connection->executeStatement('CREATE INDEX article_embedding_idx ON article USING hnsw (embedding vector_cosine_ops)');
    }

    public function testTheMigrationLeavesNothingForTheSchemaToolToChange()
    {
        $schemaTool = new SchemaTool($this->entityManager);

        $this->assertSame([], $schemaTool->getUpdateSchemaSql([$this->entityManager->getClassMetadata(Article::class)]));
    }

    public function testEmbedsOnFlushAndRanksEntitiesByDistance()
    {
        $this->persist(new Article('Fish'), new Article('Cats'), new Article('Cats and dogs'));

        $documents = $this->retrieve('cat');

        $this->assertSame(['Cats', 'Cats and dogs', 'Fish'], array_map($this->titleOf(...), $documents));
        $this->assertEqualsWithDelta(0.0, $documents[0]->getScore(), 0.0001);
    }

    public function testReadsBackTheStoredVector()
    {
        $article = $this->persist(new Article('Cats'))[0];
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(Article::class, $article->getId());

        $this->assertSame([1.01, 0.01, 0.01], $reloaded?->getEmbedding()?->getData());
    }

    public function testRanksWithTheInnerProduct()
    {
        $this->persist(new Article('Cats'), new Article('Cats cats cats'));

        $documents = (new OrmStore($this->entityManager, Article::class, Metric::InnerProduct))->query(new VectorQuery($this->vectorizer->vectorFor('cat')));

        $this->assertSame(['Cats cats cats', 'Cats'], array_map($this->titleOf(...), $documents));
    }

    public function testIndexesWhatIsMissing()
    {
        $this->entityManager->getConnection()->executeStatement("INSERT INTO article (title, views, tags) VALUES ('Dogs', 0, '[]'), ('Fish', 0, '[]')");

        (new EntityIndexer($this->entityManager, new EntityEmbedder($this->vectorizer)))->index(Article::class, ['only_missing' => true]);

        $this->assertSame(['Dogs'], array_map($this->titleOf(...), $this->retrieve('dog', 1)));
        $this->assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM article WHERE embedding IS NULL'));
    }

    /**
     * @return list<EntityVectorDocument>
     */
    private function retrieve(string $query, int $limit = 5): array
    {
        $documents = (new Retriever(new OrmStore($this->entityManager, Article::class), $this->vectorizer))->retrieve($query, ['limit' => $limit]);

        return array_map(function (mixed $document): EntityVectorDocument {
            $this->assertInstanceOf(EntityVectorDocument::class, $document);

            return $document;
        }, iterator_to_array($documents, false));
    }

    private function titleOf(EntityVectorDocument $document): string
    {
        $entity = $document->getEntity();
        $this->assertInstanceOf(Article::class, $entity);

        return $entity->getTitle();
    }

    /**
     * @return list<Article>
     */
    private function persist(Article ...$articles): array
    {
        foreach ($articles as $article) {
            $this->entityManager->persist($article);
        }

        $this->entityManager->flush();

        return array_values($articles);
    }
}

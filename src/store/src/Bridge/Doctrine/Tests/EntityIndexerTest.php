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
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Memo;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Note;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Translation;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Untracked;
use Symfony\AI\Store\Exception\InvalidArgumentException;

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

    private function indexer(): EntityIndexer
    {
        return new EntityIndexer($this->entityManager, new EntityEmbedder($this->vectorizer));
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

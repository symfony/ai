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
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Store\Bridge\Doctrine\EmbeddingListener;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\MessageLogger;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Note;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Untracked;

final class EmbeddingListenerTest extends TestCase
{
    use SqliteEntityManagerTrait;

    private EntityManagerInterface $entityManager;
    private KeywordVectorizer $vectorizer;
    private MessageLogger $logger;

    protected function setUp(): void
    {
        $this->listenWith(new KeywordVectorizer(maxLength: 20));
    }

    public function testEmbedsANewEntityWithItsInsert()
    {
        $article = new Article('Cats');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->assertSame('[1.01,0.01,0.01]', $this->storedVector('article', $article->getId()));
    }

    public function testEmbedsAgainWhenAnEmbeddedFieldChanges()
    {
        $article = $this->persisted(new Article('Cats'));

        $article->setTitle('Dogs');
        $this->entityManager->flush();

        $this->assertSame([['Cats'], ['Dogs']], $this->vectorizer->calls);
        $this->assertSame('[0.01,1.01,0.01]', $this->storedVector('article', $article->getId()));
    }

    public function testKeepsTheEmbeddingWhenAnotherFieldChanges()
    {
        $article = $this->persisted(new Article('Cats'));

        $article->setViews(42);
        $this->entityManager->flush();

        $this->assertSame([['Cats']], $this->vectorizer->calls);
    }

    public function testEmbedsAnIndexableEntityWhenASourceFieldChanges()
    {
        $note = $this->persisted(new Note('Cats'));

        $note->setTitle('Fish');
        $this->entityManager->flush();

        $this->assertSame([['Note: Cats'], ['Note: Fish']], $this->vectorizer->calls);
        $this->assertSame([0.01, 0.01, 1.01], $note->getEmbedding()?->getData());
    }

    public function testEmbedsAllEntitiesOfAFlushInOneCall()
    {
        $this->entityManager->persist(new Article('Cats'));
        $this->entityManager->persist(new Article('Dogs'));
        $this->entityManager->flush();

        $this->assertSame([['Cats', 'Dogs']], $this->vectorizer->calls);
    }

    public function testKeepsTheFlushWhenEmbeddingFails()
    {
        $article = $this->persisted(new Article('Cats and many more cats'));

        $this->assertNull($this->storedVector('article', $article->getId()));
        $this->assertSame([\sprintf('Embedding 1 "%s" entities failed, they stay without embedding until indexed again: The text exceeds 20 characters.', Article::class)], $this->logger->messages);
    }

    public function testClearsTheOutdatedEmbeddingWhenEmbeddingFails()
    {
        $article = $this->persisted(new Article('Cats'));

        $article->setTitle('Cats and many more cats');
        $this->entityManager->flush();

        $this->assertNull($article->getEmbedding());
        $this->assertNull($this->storedVector('article', $article->getId()));
    }

    public function testKeepsTheFlushWhenTheModelIsRateLimited()
    {
        $this->listenWith(new KeywordVectorizer(failure: new RateLimitExceededException(30)));

        $article = $this->persisted(new Article('Cats'));

        $this->assertNull($this->storedVector('article', $article->getId()));
        $this->assertCount(1, $this->logger->messages);
    }

    public function testFailsTheFlushWhenTheModelRejectsTheCredentials()
    {
        $this->listenWith(new KeywordVectorizer(failure: new AuthenticationException('Invalid API key.')));

        $this->expectException(AuthenticationException::class);

        $this->persisted(new Article('Cats'));
    }

    public function testEmbedsOtherClassesWhenOneFails()
    {
        $this->entityManager->persist(new Article('Cats and many more cats'));
        $note = $this->persisted(new Note('Fish'));

        $this->assertSame([0.01, 0.01, 1.01], $note->getEmbedding()?->getData());
    }

    public function testEmbedsOnlyTheGivenEntityClass()
    {
        $eventManager = new EventManager();
        $eventManager->addEventListener(Events::onFlush, new EmbeddingListener(new EntityEmbedder($this->vectorizer), entityClass: Note::class));
        $this->entityManager = $this->createEntityManager($eventManager);

        $article = $this->persisted(new Article('Cats'));
        $note = $this->persisted(new Note('Fish'));

        $this->assertNull($article->getEmbedding());
        $this->assertNotNull($note->getEmbedding());
    }

    public function testKeepsTheVectorTheIndexerSetsOnANewEntity()
    {
        $article = new Article('Cats');
        $this->entityManager->persist($article);

        $this->indexer()->index($article);

        $this->assertSame([['Cats']], $this->vectorizer->calls);
        $this->assertSame('[1.01,0.01,0.01]', $this->storedVector('article', $article->getId()));
    }

    public function testKeepsTheVectorTheIndexerSetsOnAChangedEntity()
    {
        $article = $this->persisted(new Article('Cats'));
        $article->setTitle('Dogs');

        $this->indexer()->index($article);

        $this->assertSame([['Cats'], ['Dogs']], $this->vectorizer->calls);
        $this->assertSame('[0.01,1.01,0.01]', $this->storedVector('article', $article->getId()));
    }

    public function testLeavesEntitiesWithoutEmbeddingAlone()
    {
        $this->persisted(new Untracked('Cats'));

        $this->assertSame([], $this->vectorizer->calls);
    }

    private function listenWith(KeywordVectorizer $vectorizer): void
    {
        $this->vectorizer = $vectorizer;
        $this->logger = new MessageLogger();

        $eventManager = new EventManager();
        $eventManager->addEventListener(Events::onFlush, new EmbeddingListener(new EntityEmbedder($this->vectorizer), logger: $this->logger));

        $this->entityManager = $this->createEntityManager($eventManager);
    }

    private function indexer(): EntityIndexer
    {
        return new EntityIndexer($this->entityManager, new EntityEmbedder($this->vectorizer));
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persisted(object $entity): object
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    private function storedVector(string $table, int $id): mixed
    {
        return $this->entityManager->getConnection()->fetchOne(\sprintf('SELECT embedding FROM %s WHERE id = ?', $table), [$id]);
    }
}

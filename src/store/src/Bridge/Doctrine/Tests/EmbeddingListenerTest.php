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
use Doctrine\ORM\PersistentCollection;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Store\Bridge\Doctrine\EmbeddingListener;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Album;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Genre;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Library;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\MessageLogger;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Note;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Novel;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Playlist;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Shelf;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Track;
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

    public function testEmbedsBothSidesWhenAnEntityJoinsAManyToManyAssociation()
    {
        $book = $this->persisted(new Novel('Cats'));
        $genre = $this->persisted(new Genre('Fiction'));
        $this->vectorizer->calls = [];

        $book->addGenre($genre);
        $this->entityManager->flush();

        $this->assertEqualsCanonicalizing([['Cats Fiction'], ['Fiction'.\PHP_EOL.'Cats']], $this->vectorizer->calls);
        $this->assertNotNull($this->storedVector('novel', $book->getId()));
    }

    public function testEmbedsBothSidesWhenAnEntityLeavesAManyToManyAssociation()
    {
        $book = new Novel('Cats');
        $genre = new Genre('Fiction');
        $book->addGenre($genre);
        $this->entityManager->persist($genre);
        $this->persisted($book);
        $this->vectorizer->calls = [];

        $book->removeGenre($genre);
        $this->entityManager->flush();

        $this->assertEqualsCanonicalizing([['Cats'], ['Fiction']], $this->vectorizer->calls);
    }

    public function testEmbedsBothSidesWhenAManyToManyCollectionIsReplaced()
    {
        $book = new Novel('Cats');
        $fiction = new Genre('Fiction');
        $poetry = $this->persisted(new Genre('Poetry'));
        $book->addGenre($fiction);
        $this->entityManager->persist($fiction);
        $this->persisted($book);
        $this->vectorizer->calls = [];

        $book->replaceGenres([$poetry]);
        $this->entityManager->flush();

        $this->assertEqualsCanonicalizing([['Cats Poetry'], ['Fiction', 'Poetry'.\PHP_EOL.'Cats']], $this->vectorizer->calls);
    }

    public function testEmbedsTheOldAndTheNewEntityOfAMovedToOneAssociation()
    {
        $cats = $this->persisted(new Shelf('Cats'));
        $dogs = $this->persisted(new Shelf('Dogs'));
        $book = new Novel('Fish');
        $book->setShelf($cats);
        $this->persisted($book);
        $this->vectorizer->calls = [];

        $book->setShelf($dogs);
        $this->entityManager->flush();

        $this->assertEqualsCanonicalizing([['Fish Dogs'], ['Cats', 'Dogs Fish']], $this->vectorizer->calls);
        $this->assertSame($this->vectorOf('Cats'), $this->storedVector('shelf', $cats->getId()));
        $this->assertSame($this->vectorOf('Dogs Fish'), $this->storedVector('shelf', $dogs->getId()));
    }

    public function testLetsDoctrineDeleteTheRowsOfACollectionReplacedAfterTheEntityWasEmbedded()
    {
        $book = new Novel('Cats');
        $fiction = new Genre('Fiction');
        $poetry = $this->persisted(new Genre('Poetry'));
        $book->addGenre($fiction);
        $this->entityManager->persist($fiction);
        $this->persisted($book);

        $book->replaceGenres([$poetry]);
        $this->entityManager->flush();

        $this->assertSame([$poetry->getId()], array_map('intval', $this->entityManager->getConnection()->fetchFirstColumn('SELECT genre_id FROM novel_genre')));
    }

    public function testEmbedsBothSidesWhenAManyToManyCollectionIsCleared()
    {
        $book = new Novel('Cats');
        $genre = new Genre('Fiction');
        $book->addGenre($genre);
        $this->entityManager->persist($genre);
        $this->persisted($book);
        $this->vectorizer->calls = [];

        $book->clearGenres();
        $this->entityManager->flush();

        $this->assertEqualsCanonicalizing([['Cats'], ['Fiction']], $this->vectorizer->calls);
    }

    public function testEmbedsTheEntityARemovedEntityLeftInACollectionBeforeItsRemoval()
    {
        $book = new Novel('Cats');
        $genre = new Genre('Fiction');
        $book->addGenre($genre);
        $this->entityManager->persist($genre);
        $this->persisted($book);
        $this->vectorizer->calls = [];

        $book->removeGenre($genre);
        $this->entityManager->remove($book);
        $this->entityManager->flush();

        $this->assertSame([['Fiction']], $this->vectorizer->calls);
        $this->assertSame($this->vectorOf('Fiction'), $this->storedVector('genre', $genre->getId()));
    }

    public function testEmbedsTheEntitiesOfAClearedCollectionWhoseOwnerHasAUlid()
    {
        $track = new Track('Song');
        $playlist = new Playlist('Chill');
        $playlist->addTrack($track);
        $this->entityManager->persist($track);
        $this->persisted($playlist);
        $this->vectorizer->calls = [];

        $playlist->clearTracks();
        $this->entityManager->flush();

        $this->assertSame([['Song']], $this->vectorizer->calls);
        $this->assertSame($this->vectorOf('Song'), $this->storedVector('track', $track->getId()));
    }

    public function testEmbedsTheEntitiesARemovedEntityWithACompositeIdentifierLeftBeforeItsRemoval()
    {
        $track = new Track('Song');
        $album = new Album('Band', 'Debut');
        $album->addTrack($track);
        $this->entityManager->persist($track);
        $this->persisted($album);
        $this->vectorizer->calls = [];

        $album->removeTrack($track);
        $this->entityManager->remove($album);
        $this->entityManager->flush();

        $this->assertSame([['Song']], $this->vectorizer->calls);
        $this->assertSame($this->vectorOf('Song'), $this->storedVector('track', $track->getId()));
    }

    public function testEmbedsTheEntityANewEntityJoins()
    {
        $shelf = $this->persisted(new Shelf('Cats'));
        $this->vectorizer->calls = [];

        $book = new Novel('Fish');
        $book->setShelf($shelf);
        $this->persisted($book);

        $this->assertEqualsCanonicalizing([['Fish Cats'], ['Cats Fish']], $this->vectorizer->calls);
        $this->assertSame($this->vectorOf('Cats Fish'), $this->storedVector('shelf', $shelf->getId()));
    }

    public function testEmbedsTheCollectionANewEntityJoins()
    {
        $genre = $this->persisted(new Genre('Fiction'));
        $this->vectorizer->calls = [];

        $book = new Novel('Fish');
        $book->addGenre($genre);
        $this->persisted($book);

        $this->assertEqualsCanonicalizing([['Fish Fiction'], ['Fiction'.\PHP_EOL.'Fish']], $this->vectorizer->calls);
        $this->assertSame($this->vectorOf('Fiction'.\PHP_EOL.'Fish'), $this->storedVector('genre', $genre->getId()));
    }

    public function testLeavesTheCollectionsOfARemovedEntityUnloadedWhenNoEmbeddingIsBuiltFromThem()
    {
        $library = $this->persisted(new Library());
        $shelf = new Shelf('Cats');
        $shelf->setLibrary($library);
        $this->persisted($shelf);
        $this->entityManager->clear();

        $library = $this->entityManager->find(Library::class, $library->getId());
        $this->assertNotNull($library);
        $this->entityManager->remove($library);
        $this->entityManager->flush();

        $shelves = $library->getShelves();
        $this->assertInstanceOf(PersistentCollection::class, $shelves);
        $this->assertFalse($shelves->isInitialized());
    }

    public function testLeavesAReplacedCollectionUnloadedWhenNoEmbeddingIsBuiltFromIt()
    {
        $library = $this->persisted(new Library());
        $shelf = new Shelf('Cats');
        $shelf->setLibrary($library);
        $this->persisted($shelf);
        $this->entityManager->clear();

        $library = $this->entityManager->find(Library::class, $library->getId());
        $this->assertNotNull($library);
        $shelves = $library->getShelves();
        $library->removeAllShelves();
        $this->entityManager->flush();

        $this->assertInstanceOf(PersistentCollection::class, $shelves);
        $this->assertFalse($shelves->isInitialized());
    }

    public function testEmbedsTheEntityARemovedEntityLeftBeforeItsRemoval()
    {
        $shelf = $this->persisted(new Shelf('Cats'));
        $book = new Novel('Fish');
        $book->setShelf($shelf);
        $this->persisted($book);
        $this->vectorizer->calls = [];

        $book->setShelf(null);
        $this->entityManager->remove($book);
        $this->entityManager->flush();

        $this->assertSame([['Cats']], $this->vectorizer->calls);
        $this->assertSame($this->vectorOf('Cats'), $this->storedVector('shelf', $shelf->getId()));
    }

    public function testEmbedsTheEntitiesARemovedEntityLeaves()
    {
        $shelf = $this->persisted(new Shelf('Cats'));
        $genre = $this->persisted(new Genre('Fiction'));
        $book = new Novel('Fish');
        $book->setShelf($shelf);
        $book->addGenre($genre);
        $this->persisted($book);
        $this->vectorizer->calls = [];

        // Doctrine leaves the loaded collections of the other side to the application
        $shelf->removeBook($book);
        $genre->removeBook($book);
        $this->entityManager->remove($book);
        $this->entityManager->flush();

        $this->assertEqualsCanonicalizing([['Cats'], ['Fiction']], $this->vectorizer->calls);
    }

    public function testEmbedsAnEntityOnceWhenItsFieldsAndAssociationsChange()
    {
        $book = $this->persisted(new Novel('Cats'));
        $genre = $this->persisted(new Genre('Fiction'));
        $this->vectorizer->calls = [];

        $book->setTitle('Dogs');
        $book->addGenre($genre);
        $this->entityManager->flush();

        $this->assertEqualsCanonicalizing([['Dogs Fiction'], ['Fiction'.\PHP_EOL.'Dogs']], $this->vectorizer->calls);
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

    private function vectorOf(string $text): string
    {
        return json_encode($this->vectorizer->vectorFor($text)->getData(), \JSON_THROW_ON_ERROR);
    }

    private function storedVector(string $table, int $id): mixed
    {
        return $this->entityManager->getConnection()->fetchOne(\sprintf('SELECT embedding FROM %s WHERE id = ?', $table), [$id]);
    }
}

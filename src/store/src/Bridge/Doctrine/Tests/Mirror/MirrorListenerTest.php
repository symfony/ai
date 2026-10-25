<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests\Mirror;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\Mirror\MirrorListener;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\ArrayStore;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\MessageLogger;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Place;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Product;
use Symfony\AI\Store\Bridge\Doctrine\Tests\SqliteEntityManagerTrait;
use Symfony\AI\Store\Document\Metadata;
use Symfony\Component\HttpClient\Exception\TransportException;

final class MirrorListenerTest extends TestCase
{
    use SqliteEntityManagerTrait;

    private EntityManagerInterface $entityManager;
    private KeywordVectorizer $vectorizer;
    private ArrayStore $store;
    private MessageLogger $logger;

    protected function setUp(): void
    {
        $this->listenWith(new KeywordVectorizer(maxLength: 20));
    }

    public function testMirrorsANewEntityWithItsGeneratedId()
    {
        $product = new Product('Cats');
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $this->assertCount(1, $this->store);
        $document = array_values($this->store->documents)[0];
        $this->assertSame([1.01, 0.01, 0.01], $document->getVector()->getData());
        $this->assertSame(Product::class, $document->getMetadata()[MirrorListener::METADATA_CLASS]);
        $this->assertSame((string) $product->getId(), $document->getMetadata()[MirrorListener::METADATA_ID]);
        $this->assertSame('Cats', $document->getMetadata()[Metadata::KEY_TEXT]);
    }

    public function testMirrorsAllEntitiesOfAFlushInOneCall()
    {
        $this->entityManager->persist(new Product('Cats'));
        $this->entityManager->persist(new Product('Dogs'));
        $this->entityManager->persist(new Place('Fish'));
        $this->entityManager->flush();

        $this->assertCount(3, $this->store);
        $this->assertSame([['Cats', 'Dogs'], ['Fish']], $this->vectorizer->calls);
    }

    public function testReplacesTheDocumentWhenAMirroredFieldChanges()
    {
        $product = $this->persisted(new Product('Cats'));

        $product->setName('Dogs');
        $this->entityManager->flush();

        $this->assertCount(1, $this->store);
        $this->assertSame([0.01, 1.01, 0.01], array_values($this->store->documents)[0]->getVector()->getData());
    }

    public function testKeepsTheDocumentWhenAnotherFieldChanges()
    {
        $product = $this->persisted(new Product('Cats'));

        $product->setStock(42);
        $this->entityManager->flush();

        $this->assertSame([['Cats']], $this->vectorizer->calls);
    }

    public function testRemovesTheDocumentWithTheEntity()
    {
        $product = $this->persisted(new Product('Cats'));
        $this->persisted(new Product('Dogs'));

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        $this->assertCount(1, $this->store);
        $this->assertSame([0.01, 1.01, 0.01], array_values($this->store->documents)[0]->getVector()->getData());
    }

    public function testRemovesTheDocumentOfAnEntityWithAnUlid()
    {
        $place = $this->persisted(new Place('Cats'));

        $this->entityManager->remove($place);
        $this->entityManager->flush();

        $this->assertCount(0, $this->store);
    }

    public function testRemovesTheDocumentWhenThereIsNothingLeftToEmbed()
    {
        $product = $this->persisted(new Product('Cats'));

        $product->setName('');
        $this->entityManager->flush();

        $this->assertCount(0, $this->store);
    }

    public function testKeepsTheFlushWhenEmbeddingFails()
    {
        $this->persisted(new Product('Cats and many more cats'));

        $this->assertCount(0, $this->store);
        $this->assertSame(['Mirroring 1 entities failed, they stay as they are in the store until indexed again: The text exceeds 20 characters.'], $this->logger->messages);
        $this->assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM product'));
    }

    public function testLetsAStoreFailureReachTheCaller()
    {
        $this->store->failure = new TransportException('The store is unreachable.');
        $this->entityManager->persist(new Product('Cats'));

        try {
            $this->entityManager->flush();
            $this->fail('The flush should report the store failure.');
        } catch (TransportException) {
        }

        $this->assertSame([], $this->logger->messages);
        $this->assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM product'));
    }

    public function testIgnoresEntitiesWithTheEmbeddingOnAField()
    {
        $this->persisted(new Article('Cats'));

        $this->assertCount(0, $this->store);
    }

    public function testMirrorsOnlyTheConfiguredEntityClass()
    {
        $this->listenWith(new KeywordVectorizer(), Place::class);

        $this->persisted(new Product('Cats'));
        $this->persisted(new Place('Dogs'));

        $this->assertCount(1, $this->store);
        $this->assertSame(Place::class, array_values($this->store->documents)[0]->getMetadata()[MirrorListener::METADATA_CLASS]);
    }

    public function testWritesNothingWhenTheFlushFails()
    {
        $this->entityManager->persist(new Product('Cats'));
        $this->entityManager->getConnection()->executeStatement('DROP TABLE product');

        try {
            $this->entityManager->flush();
            $this->fail('The flush should fail without the table.');
        } catch (\Throwable) {
        }

        $this->assertCount(0, $this->store);
    }

    /**
     * @param class-string|null $entityClass
     */
    private function listenWith(KeywordVectorizer $vectorizer, ?string $entityClass = null): void
    {
        $this->vectorizer = $vectorizer;
        $this->store = new ArrayStore();
        $this->logger = new MessageLogger();

        $listener = new MirrorListener(new EntityEmbedder($this->vectorizer), $this->store, $entityClass, $this->logger);
        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onFlush, Events::postFlush], $listener);

        $this->entityManager = $this->createEntityManager($eventManager);
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
}

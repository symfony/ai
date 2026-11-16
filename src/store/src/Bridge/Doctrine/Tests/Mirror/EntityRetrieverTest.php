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

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Bridge\Doctrine\Mirror\EntityRetriever;
use Symfony\AI\Store\Bridge\Doctrine\Mirror\MirrorListener;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Place;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Product;
use Symfony\AI\Store\Bridge\Doctrine\Tests\SqliteEntityManagerTrait;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\RetrieverInterface;

final class EntityRetrieverTest extends TestCase
{
    use SqliteEntityManagerTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createEntityManager();
    }

    public function testReturnsTheEntitiesInTheOrderOfTheHits()
    {
        $cats = new Product('Cats');
        $dogs = new Product('Dogs');
        $fish = new Place('Fish');
        $this->persist($cats, $dogs, $fish);

        $documents = $this->retrieve([
            $this->hit(Product::class, (string) $dogs->getId(), 0.1),
            $this->hit(Place::class, (string) $fish->getId(), 0.2),
            $this->hit(Product::class, (string) $cats->getId(), 0.3),
        ]);

        $this->assertContainsOnlyInstancesOf(EntityVectorDocument::class, $documents);
        $this->assertSame([$dogs, $fish, $cats], array_map(static fn (EntityVectorDocument $document): object => $document->getEntity(), $documents));
        $this->assertSame([0.1, 0.2, 0.3], array_map(static fn (EntityVectorDocument $document): ?float => $document->getScore(), $documents));
        $this->assertSame([(string) $dogs->getId(), (string) $fish->getId(), (string) $cats->getId()], array_map(static fn (EntityVectorDocument $document): int|string => $document->getId(), $documents));
        $this->assertSame('Dogs', $documents[0]->getMetadata()[Metadata::KEY_TEXT]);
    }

    public function testReadsTheIdOfAJsonStoreAsAnInteger()
    {
        $cats = new Product('Cats');
        $this->persist($cats);

        $documents = $this->retrieve([$this->hit(Product::class, $cats->getId(), 0.1)]);

        $this->assertSame([$cats], array_map(static fn (EntityVectorDocument $document): object => $document->getEntity(), $documents));
    }

    public function testSkipsHitsWithoutAnEntity()
    {
        $cats = new Product('Cats');
        $this->persist($cats);
        $skipped = [];

        $documents = $this->retrieve([
            $this->hit(Product::class, '999', 0.1),
            $this->hit(Product::class, (string) $cats->getId(), 0.2),
            new VectorDocument('plain', new Vector([1.0]), new Metadata(['title' => 'No entity'])),
        ], static function (VectorDocumentInterface $document) use (&$skipped): void {
            $skipped[] = $document->getId();
        });

        $this->assertCount(1, $documents);
        $this->assertSame(['product-999', 'plain'], $skipped);
    }

    public function testSkipsHitsOfClassesThatAreNotMirrored()
    {
        $article = new Article('Cats');
        $this->persist($article);

        $this->assertSame([], $this->retrieve([
            $this->hit(Article::class, (string) $article->getId(), 0.1),
            $this->hit(\stdClass::class, '1', 0.1),
            $this->hit('Unknown\\Entity', '1', 0.1),
        ]));
    }

    /**
     * @param list<VectorDocumentInterface>                  $hits
     * @param (callable(VectorDocumentInterface): void)|null $onSkipped
     *
     * @return list<EntityVectorDocument>
     */
    private function retrieve(array $hits, ?callable $onSkipped = null): array
    {
        $inner = new class($hits) implements RetrieverInterface {
            /**
             * @param list<VectorDocumentInterface> $hits
             */
            public function __construct(private readonly array $hits)
            {
            }

            public function retrieve(string $query, array $options = []): iterable
            {
                return $this->hits;
            }
        };

        return [...(new EntityRetriever($inner, $this->entityManager, $onSkipped))->retrieve('cats')];
    }

    private function hit(string $class, int|string $id, float $score): VectorDocument
    {
        return new VectorDocument(
            'product-'.$id,
            new Vector([1.0, 0.0]),
            new Metadata([MirrorListener::METADATA_CLASS => $class, MirrorListener::METADATA_ID => $id]),
            $score,
        );
    }

    private function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }
}

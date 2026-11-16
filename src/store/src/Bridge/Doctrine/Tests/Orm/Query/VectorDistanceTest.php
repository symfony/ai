<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests\Orm\Query;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\AI\Store\Bridge\Doctrine\Orm\Query\VectorDistance;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\SqliteEntityManagerTrait;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class VectorDistanceTest extends TestCase
{
    use SqliteEntityManagerTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createEntityManager();
        $this->entityManager->getConfiguration()->addCustomNumericFunction(VectorDistance::NAME, VectorDistance::class);

        foreach (['Fish', 'Cats', 'Cats and dogs'] as $title) {
            $this->entityManager->persist(new Article($title));
        }
        $this->entityManager->flush();

        (new EntityIndexer($this->entityManager, new EntityEmbedder(new KeywordVectorizer())))->index(Article::class);
    }

    public function testOrdersEntitiesByDistance()
    {
        $articles = $this->entityManager
            ->createQuery('SELECT a FROM '.Article::class.' a WHERE a.title != :title ORDER BY VECTOR_DISTANCE(a.embedding, :vector) ASC')
            ->setParameter('title', 'Cats')
            ->setParameter('vector', (new KeywordVectorizer())->vectorFor('cat'), VectorType::NAME)
            ->getResult();

        $this->assertSame(['Cats and dogs', 'Fish'], array_map(static fn (Article $article): string => $article->getTitle(), $articles));
    }

    public function testSelectsTheDistanceForTheGivenMetric()
    {
        $distance = $this->entityManager
            ->createQuery('SELECT VECTOR_DISTANCE(a.embedding, :vector, \'euclidean\') FROM '.Article::class.' a WHERE a.title = :title')
            ->setParameter('title', 'Cats')
            ->setParameter('vector', (new KeywordVectorizer())->vectorFor('cats cats cats'), VectorType::NAME)
            ->getSingleScalarResult();

        $this->assertEqualsWithDelta(2.0, (float) $distance, 0.0001);
    }

    public function testRefusesAnUnknownMetric()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be one of "cosine", "euclidean", "inner_product"');

        $this->entityManager
            ->createQuery('SELECT VECTOR_DISTANCE(a.embedding, :vector, \'manhattan\') FROM '.Article::class.' a')
            ->getSQL();
    }
}

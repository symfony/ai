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

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;
use Symfony\AI\Store\Bridge\Doctrine\Orm\Query\VectorDistance;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;

final class MariaDbVectorDistanceTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        if (!Type::hasType(VectorType::NAME)) {
            Type::addType(VectorType::NAME, VectorType::class);
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/../../Fixtures'], true);
        $configuration->addCustomNumericFunction(VectorDistance::NAME, VectorDistance::class);

        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        // the server version selects the platform, so the query is built without a connection
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => 'mariadb-11.8.0'], $configuration);

        $this->entityManager = new EntityManager($connection, $configuration);
    }

    public function testWrapsAParameterWithoutAType()
    {
        $sql = $this->sql($this->entityManager
            ->createQuery('SELECT a FROM '.Article::class.' a ORDER BY VECTOR_DISTANCE(a.embedding, :vector) ASC'));

        $this->assertStringContainsString('VEC_DISTANCE_COSINE(', $sql);
        $this->assertSame(1, substr_count($sql, 'VEC_FromText('));
    }

    public function testWrapsAParameterWithTheVectorTypeOnce()
    {
        $query = $this->entityManager
            ->createQuery('SELECT a FROM '.Article::class.' a ORDER BY VECTOR_DISTANCE(a.embedding, :vector) ASC')
            ->setParameter('vector', [1.0, 0.0], VectorType::NAME);

        $sql = $this->sql($query);

        $this->assertStringContainsString('VEC_DISTANCE_COSINE(', $sql);
        $this->assertStringNotContainsString('VEC_FromText(VEC_FromText(', $sql);
        $this->assertSame(1, substr_count($sql, 'VEC_FromText('));
    }

    private function sql(Query $query): string
    {
        $sql = $query->getSQL();

        return \is_array($sql) ? implode(' ', $sql) : $sql;
    }
}

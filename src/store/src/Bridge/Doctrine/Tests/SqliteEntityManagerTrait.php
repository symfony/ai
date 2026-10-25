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
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Memo;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Note;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Untracked;
use Symfony\Bridge\Doctrine\Types\UlidType;

/**
 * An in-memory SQLite entity manager, with the sqlite-vec distance functions implemented in PHP.
 */
trait SqliteEntityManagerTrait
{
    private function createEntityManager(?EventManager $eventManager = null): EntityManagerInterface
    {
        if (!Type::hasType(VectorType::NAME)) {
            Type::addType(VectorType::NAME, VectorType::class);
        }

        if (!Type::hasType(UlidType::NAME)) {
            Type::addType(UlidType::NAME, UlidType::class);
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/Fixtures'], true);

        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);

        $pdo = $connection->getNativeConnection();
        \assert($pdo instanceof \PDO);
        $this->registerFunction($pdo, 'vec_distance_cosine', static function (string $a, string $b): float {
            [$a, $b] = [json_decode($a, true), json_decode($b, true)];
            $dot = array_sum(array_map(static fn (float $x, float $y): float => $x * $y, $a, $b));

            return 1 - $dot / (sqrt(array_sum(array_map(static fn (float $x): float => $x ** 2, $a))) * sqrt(array_sum(array_map(static fn (float $y): float => $y ** 2, $b))));
        });
        $this->registerFunction($pdo, 'vec_distance_L2', static function (string $a, string $b): float {
            [$a, $b] = [json_decode($a, true), json_decode($b, true)];

            return sqrt(array_sum(array_map(static fn (float $x, float $y): float => ($x - $y) ** 2, $a, $b)));
        });

        $entityManager = new EntityManager($connection, $configuration, $eventManager);

        (new SchemaTool($entityManager))->createSchema($this->schemaMetadata($entityManager));

        return $entityManager;
    }

    /**
     * @return list<ClassMetadata<object>>
     */
    private function schemaMetadata(EntityManagerInterface $entityManager): array
    {
        return array_map($entityManager->getClassMetadata(...), [Article::class, Memo::class, Note::class, Untracked::class]);
    }

    private function registerFunction(\PDO $pdo, string $name, \Closure $function): void
    {
        if ($pdo instanceof \Pdo\Sqlite) {
            $pdo->createFunction($name, $function, 2);

            return;
        }

        $pdo->sqliteCreateFunction($name, $function, 2);
    }
}

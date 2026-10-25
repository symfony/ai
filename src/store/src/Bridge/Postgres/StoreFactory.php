<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Postgres;

use Doctrine\DBAL\Connection;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class StoreFactory
{
    public static function createStoreFromPdo(
        \PDO $connection,
        string $tableName,
        string $vectorFieldName = 'embedding',
        Metric $metric = Metric::L2,
        string $lang = 'english',
        int $dimensions = 1536,
        string $vectorType = 'vector',
        string $indexMethod = 'hnsw',
        ?string $indexOpclass = null,
    ): ManagedStoreInterface&StoreInterface {
        return new Store($connection, $tableName, $vectorFieldName, $metric, $lang, $dimensions, $vectorType, $indexMethod, $indexOpclass);
    }

    public static function createStoreFromDbal(
        Connection $connection,
        string $tableName,
        string $vectorFieldName = 'embedding',
        Metric $metric = Metric::L2,
        string $lang = 'english',
        int $dimensions = 1536,
        string $vectorType = 'vector',
        string $indexMethod = 'hnsw',
        ?string $indexOpclass = null,
    ): ManagedStoreInterface&StoreInterface {
        $pdo = $connection->getNativeConnection();

        if (!$pdo instanceof \PDO) {
            throw new InvalidArgumentException('Only DBAL connections using PDO driver are supported.');
        }

        return static::createStoreFromPdo($pdo, $tableName, $vectorFieldName, $metric, $lang, $dimensions, $vectorType, $indexMethod, $indexOpclass);
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Sqlite;

use Symfony\AI\Store\Distance\DistanceCalculator;
use Symfony\AI\Store\Distance\DistanceStrategy;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class StoreFactory
{
    public static function create(
        string $dsn,
        string $tableName,
        ?DistanceStrategy $strategy = null,
    ): Store {
        $pdo = self::createPdo($dsn);

        $distanceCalculator = null !== $strategy
            ? new DistanceCalculator($strategy)
            : new DistanceCalculator();

        return new Store($pdo, $tableName, $distanceCalculator);
    }

    public static function createVecStore(
        string $dsn,
        string $tableName,
        Distance $distance = Distance::Cosine,
        int $vectorDimension = 1536,
    ): VecStore {
        $pdo = self::createPdo($dsn);

        return new VecStore($pdo, $tableName, $distance, $vectorDimension);
    }

    private static function createPdo(string $dsn): \PDO
    {
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}

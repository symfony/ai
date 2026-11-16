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
        $distanceCalculator = null !== $strategy
            ? new DistanceCalculator($strategy)
            : new DistanceCalculator();

        return new Store($dsn, $tableName, $distanceCalculator);
    }

    public static function createVecStore(
        string $dsn,
        string $tableName,
        Metric $metric = Metric::Cosine,
        int $dimensions = 1536,
    ): VecStore {
        return new VecStore($dsn, $tableName, $metric, $dimensions);
    }
}

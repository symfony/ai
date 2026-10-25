<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms;

use Symfony\AI\Store\Bridge\Doctrine\Dbal\Metric;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Requires MariaDB 11.7 or newer.
 *
 * @see https://mariadb.com/docs/server/reference/sql-structure/vectors/vector-overview
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MariaDbVectorPlatform implements VectorPlatformInterface
{
    use VectorLiteralTrait;

    public function getColumnDeclarationSql(): string
    {
        // the server needs the size, which a "columnDefinition" of the column adds and DBAL leaves out when comparing schemas
        return 'VECTOR';
    }

    public function getVectorParameterSql(string $parameter): string
    {
        return \sprintf('VEC_FromText(%s)', $parameter);
    }

    public function getVectorSelectSql(string $column): string
    {
        return \sprintf('VEC_ToText(%s)', $column);
    }

    public function getDistanceSql(string $column, string $parameterSql, Metric $metric): string
    {
        $function = match ($metric) {
            Metric::Cosine => 'VEC_DISTANCE_COSINE',
            Metric::Euclidean => 'VEC_DISTANCE_EUCLIDEAN',
            Metric::InnerProduct => throw new InvalidArgumentException('MariaDB does not implement the inner product metric, use cosine or euclidean instead.'),
        };

        return \sprintf('%s(%s, %s)', $function, $column, $parameterSql);
    }
}

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

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Metric;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Stores vectors as JSON text, so only similarity queries need the sqlite-vec extension.
 *
 * @see https://github.com/asg017/sqlite-vec
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SqliteVectorPlatform implements VectorPlatformInterface
{
    public function getColumnDeclarationSql(): string
    {
        // DBAL introspects TEXT as a text column, which SQLite declares as CLOB
        return 'CLOB';
    }

    public function getVectorParameterSql(string $parameter): string
    {
        return $parameter;
    }

    public function getVectorSelectSql(string $column): string
    {
        return $column;
    }

    public function getDistanceSql(string $column, string $parameterSql, Metric $metric): string
    {
        $function = match ($metric) {
            Metric::Cosine => 'vec_distance_cosine',
            Metric::Euclidean => 'vec_distance_L2',
            Metric::InnerProduct => throw new InvalidArgumentException('sqlite-vec does not implement the inner product metric, use cosine or euclidean instead.'),
        };

        return \sprintf('%s(%s, %s)', $function, $column, $parameterSql);
    }

    public function toDatabaseValue(VectorInterface $vector): string
    {
        return json_encode($vector->getData(), \JSON_THROW_ON_ERROR);
    }

    public function toVector(string $value): VectorInterface
    {
        $data = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            throw new InvalidArgumentException(\sprintf('Expected a vector literal, got "%s".', $value));
        }

        return new Vector(array_map(floatval(...), array_values($data)));
    }
}

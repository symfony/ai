<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\VectorPlatform;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Distance;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Stores vectors as JSON text in a plain SQLite column.
 *
 * Storage needs nothing but stock SQLite, so a schema carrying vectors can be created and written
 * against it - which is what a test suite running on SQLite needs. Only the distance functions come
 * from the sqlite-vec extension, and they read the same JSON text, so a similarity query is the one
 * operation that requires the extension to be loaded.
 *
 * @see https://github.com/asg017/sqlite-vec
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SqliteVectorPlatform implements VectorPlatformInterface
{
    public function getColumnDeclarationSql(int $dimensions): string
    {
        return 'TEXT';
    }

    public function getUnsizedColumnDeclarationSql(): string
    {
        return 'TEXT';
    }

    public function getVectorParameterSql(string $parameter): string
    {
        return $parameter;
    }

    public function getVectorSelectSql(string $column): string
    {
        return $column;
    }

    public function getDistanceSql(string $column, string $parameterSql, Distance $distance): string
    {
        $function = match ($distance) {
            Distance::Cosine => 'vec_distance_cosine',
            Distance::Euclidean => 'vec_distance_L2',
            Distance::InnerProduct => throw new InvalidArgumentException('sqlite-vec does not implement an inner product distance, use cosine or euclidean instead.'),
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

    public function getSetupSql(string $table, string $column, string $indexName, int $dimensions, Distance $distance): array
    {
        return [
            \sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $this->getColumnDeclarationSql($dimensions)),
        ];
    }

    public function getDropSql(string $table, string $column, string $indexName): array
    {
        return [
            \sprintf('ALTER TABLE %s DROP COLUMN %s', $table, $column),
        ];
    }
}

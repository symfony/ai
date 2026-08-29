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
 * Requires MariaDB >= 11.7.
 *
 * @see https://mariadb.org/rag-with-mariadb-vector/
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MariaDbVectorPlatform implements VectorPlatformInterface
{
    public function getColumnDeclarationSql(int $dimensions): string
    {
        return \sprintf('VECTOR(%d)', $dimensions);
    }

    public function getUnsizedColumnDeclarationSql(): string
    {
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

    public function getDistanceSql(string $column, string $parameterSql, Distance $distance): string
    {
        $function = match ($distance) {
            Distance::Cosine => 'VEC_DISTANCE_COSINE',
            Distance::Euclidean => 'VEC_DISTANCE_EUCLIDEAN',
            Distance::InnerProduct => throw new InvalidArgumentException('MariaDB does not implement an inner product distance, use cosine or euclidean instead.'),
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
            \sprintf('ALTER TABLE %s ADD COLUMN IF NOT EXISTS %s %s', $table, $column, $this->getColumnDeclarationSql($dimensions)),
            // The column is normally created unsized by the schema tooling from the entity
            // mapping, and an unsized vector column cannot be indexed.
            \sprintf('ALTER TABLE %s MODIFY COLUMN %s %s', $table, $column, $this->getColumnDeclarationSql($dimensions)),
            \sprintf('ALTER TABLE %s ADD VECTOR INDEX %s (%s)', $table, $indexName, $column),
        ];
    }

    public function getDropSql(string $table, string $column, string $indexName): array
    {
        return [
            \sprintf('ALTER TABLE %s DROP INDEX %s', $table, $indexName),
            \sprintf('ALTER TABLE %s DROP COLUMN %s', $table, $column),
        ];
    }
}

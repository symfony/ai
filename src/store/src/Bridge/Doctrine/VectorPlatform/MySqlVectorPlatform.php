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
 * Requires MySQL >= 9.0.
 *
 * MySQL has no vector index, so queries fall back to an exact scan of the table.
 *
 * @see https://dev.mysql.com/doc/refman/9.0/en/vector-functions.html
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MySqlVectorPlatform implements VectorPlatformInterface
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
        return \sprintf('STRING_TO_VECTOR(%s)', $parameter);
    }

    public function getVectorSelectSql(string $column): string
    {
        return \sprintf('VECTOR_TO_STRING(%s)', $column);
    }

    public function getDistanceSql(string $column, string $parameterSql, Distance $distance): string
    {
        $metric = match ($distance) {
            Distance::Cosine => 'COSINE',
            Distance::Euclidean => 'EUCLIDEAN',
            Distance::InnerProduct => 'DOT',
        };

        return \sprintf("DISTANCE(%s, %s, '%s')", $column, $parameterSql, $metric);
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
            // The column is normally created unsized by the schema tooling from the entity
            // mapping; sizing it is what makes it usable as a vector.
            \sprintf('ALTER TABLE %s MODIFY COLUMN %s %s', $table, $column, $this->getColumnDeclarationSql($dimensions)),
        ];
    }

    public function getDropSql(string $table, string $column, string $indexName): array
    {
        return [
            \sprintf('ALTER TABLE %s DROP COLUMN %s', $table, $column),
        ];
    }
}

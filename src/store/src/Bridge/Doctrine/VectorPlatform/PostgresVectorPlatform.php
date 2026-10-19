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
 * Requires PostgreSQL with the pgvector extension.
 *
 * @see https://github.com/pgvector/pgvector
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PostgresVectorPlatform implements VectorPlatformInterface
{
    public function getColumnDeclarationSql(int $dimensions): string
    {
        return \sprintf('vector(%d)', $dimensions);
    }

    public function getUnsizedColumnDeclarationSql(): string
    {
        return 'vector';
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
        $operator = match ($distance) {
            Distance::Cosine => '<=>',
            Distance::Euclidean => '<->',
            Distance::InnerProduct => '<#>',
        };

        return \sprintf('(%s %s %s)', $column, $operator, $parameterSql);
    }

    public function toDatabaseValue(VectorInterface $vector): string
    {
        return json_encode($vector->getData(), \JSON_THROW_ON_ERROR);
    }

    public function toVector(string $value): VectorInterface
    {
        $data = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            throw new InvalidArgumentException(\sprintf('Expected a pgvector literal, got "%s".', $value));
        }

        return new Vector(array_map(floatval(...), array_values($data)));
    }

    public function getSetupSql(string $table, string $column, string $indexName, int $dimensions, Distance $distance): array
    {
        $opclass = match ($distance) {
            Distance::Cosine => 'vector_cosine_ops',
            Distance::Euclidean => 'vector_l2_ops',
            Distance::InnerProduct => 'vector_ip_ops',
        };

        $definition = \sprintf('USING hnsw (%s %s)', $column, $opclass);

        return [
            'CREATE EXTENSION IF NOT EXISTS vector',
            // Replaces the btree index the schema tool creates for an index declared on the entity
            \sprintf(
                "DO $$ BEGIN IF EXISTS (SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = '%s' AND indexdef NOT LIKE '%%%s') THEN DROP INDEX %s; END IF; END $$",
                str_replace("'", "''", $indexName),
                str_replace("'", "''", $definition),
                $indexName,
            ),
            \sprintf('ALTER TABLE %s ALTER COLUMN %s TYPE %s', $table, $column, $this->getColumnDeclarationSql($dimensions)),
            \sprintf('CREATE INDEX IF NOT EXISTS %s ON %s %s', $indexName, $table, $definition),
        ];
    }

    public function getDropSql(string $indexName): array
    {
        return [
            \sprintf('DROP INDEX IF EXISTS %s', $indexName),
        ];
    }
}

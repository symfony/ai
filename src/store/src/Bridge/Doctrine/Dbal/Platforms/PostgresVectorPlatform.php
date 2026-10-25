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
 * Requires PostgreSQL with the pgvector extension.
 *
 * @see https://github.com/pgvector/pgvector
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PostgresVectorPlatform implements VectorPlatformInterface
{
    public function getColumnDeclarationSql(): string
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

    public function getDistanceSql(string $column, string $parameterSql, Metric $metric): string
    {
        $operator = match ($metric) {
            Metric::Cosine => '<=>',
            Metric::Euclidean => '<->',
            Metric::InnerProduct => '<#>',
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
}

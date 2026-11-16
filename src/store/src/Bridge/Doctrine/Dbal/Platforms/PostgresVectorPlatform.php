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

/**
 * Requires PostgreSQL with the pgvector extension.
 *
 * @see https://github.com/pgvector/pgvector
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PostgresVectorPlatform implements VectorPlatformInterface
{
    use VectorLiteralTrait;

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
}

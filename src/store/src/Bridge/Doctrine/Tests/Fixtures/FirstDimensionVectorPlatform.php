<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures;

use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Distance;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\SqliteVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\VectorPlatformInterface;

/**
 * Ranks by the first dimension with SQLite's built-in JSON functions, so queries run without sqlite-vec.
 */
final class FirstDimensionVectorPlatform implements VectorPlatformInterface
{
    private SqliteVectorPlatform $sqlite;

    public function __construct()
    {
        $this->sqlite = new SqliteVectorPlatform();
    }

    public function getColumnDeclarationSql(int $dimensions): string
    {
        return $this->sqlite->getColumnDeclarationSql($dimensions);
    }

    public function getUnsizedColumnDeclarationSql(): string
    {
        return $this->sqlite->getUnsizedColumnDeclarationSql();
    }

    public function getVectorParameterSql(string $parameter): string
    {
        return $this->sqlite->getVectorParameterSql($parameter);
    }

    public function getVectorSelectSql(string $column): string
    {
        return $this->sqlite->getVectorSelectSql($column);
    }

    public function getDistanceSql(string $column, string $parameterSql, Distance $distance): string
    {
        return \sprintf("ABS(json_extract(%s, '$[0]') - json_extract(%s, '$[0]'))", $column, $parameterSql);
    }

    public function toDatabaseValue(VectorInterface $vector): string
    {
        return $this->sqlite->toDatabaseValue($vector);
    }

    public function toVector(string $value): VectorInterface
    {
        return $this->sqlite->toVector($value);
    }

    public function getSetupSql(string $table, string $column, string $indexName, int $dimensions, Distance $distance): array
    {
        return [];
    }

    public function getDropSql(string $indexName): array
    {
        return [];
    }
}

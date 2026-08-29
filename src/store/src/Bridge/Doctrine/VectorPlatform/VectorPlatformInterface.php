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

use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Distance;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Translates vector operations into the SQL dialect of a single database platform.
 *
 * Vector support is not standardized: pgvector overloads operators, MariaDB and MySQL expose
 * functions, and each spells the column type, the conversion to and from text, and the index
 * differently. This interface isolates those differences from the store.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface VectorPlatformInterface
{
    /**
     * The column type declaration for a vector of the given size, e.g. `vector(1536)`.
     *
     * @param positive-int $dimensions
     */
    public function getColumnDeclarationSql(int $dimensions): string;

    /**
     * The column type declaration for a vector of unknown size.
     *
     * No supported platform reports the size of a vector column during introspection, so this is
     * what a column read back from the database declares itself as.
     */
    public function getUnsizedColumnDeclarationSql(): string;

    /**
     * Wraps a bound parameter so the platform reads it as a vector, e.g. `VEC_FromText(:vector)`.
     */
    public function getVectorParameterSql(string $parameter): string;

    /**
     * Wraps a column so that selecting it yields a value `toVector()` understands.
     */
    public function getVectorSelectSql(string $column): string;

    /**
     * The expression yielding the distance between a column and a vector parameter.
     *
     * @throws InvalidArgumentException if the platform does not implement the given distance
     */
    public function getDistanceSql(string $column, string $parameterSql, Distance $distance): string;

    /**
     * The value to bind for a parameter wrapped by `getVectorParameterSql()`.
     */
    public function toDatabaseValue(VectorInterface $vector): string;

    /**
     * The inverse of `toDatabaseValue()`, reading a column selected via `getVectorSelectSql()`.
     */
    public function toVector(string $value): VectorInterface;

    /**
     * The statements adding a vector column and its index to an existing table.
     *
     * @param positive-int $dimensions
     *
     * @return list<string>
     */
    public function getSetupSql(string $table, string $column, string $indexName, int $dimensions, Distance $distance): array;

    /**
     * The statements removing the vector column and its index again.
     *
     * @return list<string>
     */
    public function getDropSql(string $table, string $column, string $indexName): array;
}

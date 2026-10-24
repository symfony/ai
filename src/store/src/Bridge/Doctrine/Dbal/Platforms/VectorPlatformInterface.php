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

use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Metric;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Translates vector operations into the SQL dialect of a single database platform.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface VectorPlatformInterface
{
    /**
     * The column type declaration of a vector, unsized as introspection reports it.
     */
    public function getColumnDeclarationSql(): string;

    /**
     * Wraps a bound parameter so the platform reads it as a vector, e.g. `vec_f32(:vector)`.
     */
    public function getVectorParameterSql(string $parameter): string;

    /**
     * Wraps a column so that selecting it yields a value `toVector()` understands.
     */
    public function getVectorSelectSql(string $column): string;

    /**
     * The expression yielding the distance between a column and a vector parameter.
     *
     * @throws InvalidArgumentException if the platform does not implement the given metric
     */
    public function getDistanceSql(string $column, string $parameterSql, Metric $metric): string;

    /**
     * The value to bind for a parameter wrapped by `getVectorParameterSql()`.
     */
    public function toDatabaseValue(VectorInterface $vector): string;

    /**
     * The inverse of `toDatabaseValue()`, reading a column selected via `getVectorSelectSql()`.
     */
    public function toVector(string $value): VectorInterface;
}

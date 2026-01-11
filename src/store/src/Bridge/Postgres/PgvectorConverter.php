<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Postgres;

use Symfony\AI\Platform\Vector\VectorInterface;

/**
 * Utility class for converting between Vector objects and pgvector string format.
 *
 * @internal
 *
 * @author Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 */
final class PgvectorConverter
{
    /**
     * Convert a Vector to pgvector string format.
     *
     * @example [0.1, 0.2, 0.3] becomes "[0.1,0.2,0.3]"
     */
    public static function toPgvector(VectorInterface $vector): string
    {
        return '['.implode(',', $vector->getData()).']';
    }

    /**
     * Convert a pgvector string to a float array.
     *
     * @return float[]
     */
    public static function fromPgvector(string $vector): array
    {
        return json_decode($vector, true, 512, \JSON_THROW_ON_ERROR);
    }
}

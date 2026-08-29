<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine;

/**
 * A Doctrine entity that knows which of its data goes into its embedding.
 *
 * Indexing and querying both go through this method, so the vector written to the row and the
 * vector a "find me something like this" query is built from describe the entity the same way.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface IndexableEntityInterface
{
    /**
     * The text representation of this entity that gets embedded.
     */
    public function getIndexableContent(): string;
}

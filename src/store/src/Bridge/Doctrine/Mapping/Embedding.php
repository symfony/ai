<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Mapping;

/**
 * Marks the entity field holding the embedding, or the entity class to mirror into another store.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS)]
final class Embedding
{
    /**
     * @param non-empty-list<string> $from the fields the embedding is built from, embedded again when one of them changes
     */
    public function __construct(
        public readonly array $from,
    ) {
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document;

use Symfony\AI\Platform\Vector\VectorInterface;

/**
 * A document that carries a vector, as stored in and returned by a StoreInterface.
 *
 * Implement this interface to let a store hand back richer objects than the shipped VectorDocument,
 * for example a document that keeps a reference to the domain object it was created from.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface VectorDocumentInterface
{
    public function getId(): int|string;

    public function getVector(): VectorInterface;

    public function getMetadata(): Metadata;

    /**
     * The distance to the queried vector, if this document was returned by a similarity query.
     */
    public function getScore(): ?float;

    /**
     * Returns a new instance with the given score.
     */
    public function withScore(float $score): self;
}

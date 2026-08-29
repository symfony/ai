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

use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocumentInterface;

/**
 * A vector document that keeps the entity it belongs to.
 *
 * The Doctrine store hands these back, so a caller can render the matched entities right away
 * instead of collecting ids from the result and loading them in a second round trip.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EntityVectorDocument implements VectorDocumentInterface
{
    public function __construct(
        private readonly object $entity,
        private readonly int|string $id,
        private readonly VectorInterface $vector,
        private readonly Metadata $metadata = new Metadata(),
        private readonly ?float $score = null,
    ) {
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getVector(): VectorInterface
    {
        return $this->vector;
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function withScore(float $score): self
    {
        return new self($this->entity, $this->id, $this->vector, $this->metadata, $score);
    }
}

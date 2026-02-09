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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VectorDocument
{
    public function __construct(
        private readonly int|string $id,
        private readonly VectorInterface $vector,
        private readonly Metadata $metadata = new Metadata(),
        private readonly ?float $score = null,
    ) {
    }

    /**
     * Returns a new instance with the given score.
     */
    public function withScore(float $score): self
    {
        return new self(
            id: $this->id,
            vector: $this->vector,
            metadata: $this->metadata,
            score: $score,
        );
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
}

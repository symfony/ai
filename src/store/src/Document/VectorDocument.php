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
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VectorDocument
{
    public function __construct(
        public readonly Uuid $id,
        public readonly VectorInterface $vector,
        public readonly Metadata $metadata = new Metadata(),
        public readonly ?float $score = null,
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
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChunkEvent extends Event
{
    private bool $skipChunk = false;
    private mixed $chunk = null;

    public function setChunk(mixed $chunk): void
    {
        $this->chunk = $chunk;
    }

    public function hasChunk(): bool
    {
        return null !== $this->chunk;
    }

    public function getChunk(): mixed
    {
        return $this->chunk;
    }

    public function skipChunk(): void
    {
        $this->skipChunk = true;
    }

    public function isChunkSkipped(): bool
    {
        return $this->skipChunk;
    }
}

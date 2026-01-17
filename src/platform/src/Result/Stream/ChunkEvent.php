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

use Symfony\AI\Platform\Result\StreamResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChunkEvent extends Event
{
    private bool $skipChunk = false;

    public function __construct(
        StreamResult $result,
        private mixed $chunk,
    ) {
        parent::__construct($result);
    }

    public function setChunk(mixed $chunk): void
    {
        $this->chunk = $chunk;
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

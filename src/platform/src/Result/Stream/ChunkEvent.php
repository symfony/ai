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

    /** @var \Closure(): void|null */
    private ?\Closure $afterChunkConsumedCallback = null;

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

    /**
     * Registers a callback to be invoked after the replacement chunk is fully consumed.
     *
     * This is useful for propagating metadata from nested results after their
     * content has been yielded.
     *
     * @param \Closure(): void $callback
     */
    public function afterChunkConsumed(\Closure $callback): void
    {
        $this->afterChunkConsumedCallback = $callback;
    }

    /**
     * @internal
     */
    public function getAfterChunkConsumedCallback(): ?\Closure
    {
        return $this->afterChunkConsumedCallback;
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

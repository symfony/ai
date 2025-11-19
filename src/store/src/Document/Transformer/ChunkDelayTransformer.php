<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Transformer;

use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * This transformer splits the batch of documents into chunks and delays in-between with x seconds, which is useful
 * when indexing a lot of documents and facing API rate limits.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChunkDelayTransformer implements TransformerInterface
{
    public const OPTION_CHUNK_SIZE = 'chunk_size';
    public const OPTION_DELAY = 'delay';

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array{chunk_size?: int, delay?: int} $options
     */
    public function transform(iterable $documents, array $options = []): iterable
    {
        $chunkSize = $options[self::OPTION_CHUNK_SIZE] ?? 50;
        $delay = $options[self::OPTION_DELAY] ?? 10;

        $counter = 0;
        foreach ($documents as $document) {
            yield $document;
            ++$counter;

            if ($chunkSize === $counter && 0 !== $delay) {
                $this->clock->sleep($delay);
            }
        }
    }
}

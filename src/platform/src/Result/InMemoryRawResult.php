<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * A fake implementation of RawResultInterface that returns fixed data.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class InMemoryRawResult implements RawResultInterface
{
    /**
     * @param array<string, mixed>           $data
     * @param iterable<array<string, mixed>> $dataStream
     */
    public function __construct(
        private readonly array $data = [],
        private readonly iterable $dataStream = [],
        private readonly object $object = new \stdClass(),
    ) {
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDataStream(): iterable
    {
        yield from $this->dataStream;
    }

    public function getObject(): object
    {
        return $this->object;
    }
}

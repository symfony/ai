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
 * The outcome of a batch of requests, one {@see BatchItem} per request.
 *
 * The items are yielded while the provider's result file is read, so the content is a one-shot traversal.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BatchResult extends BaseResult
{
    /**
     * @param iterable<BatchItem> $items
     */
    public function __construct(
        private readonly iterable $items,
    ) {
    }

    /**
     * @return iterable<BatchItem>
     */
    public function getContent(): iterable
    {
        return $this->items;
    }
}

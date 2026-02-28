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

use Symfony\AI\Platform\Reranking\RerankingEntry;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RerankingResult extends BaseResult
{
    /**
     * @var list<RerankingEntry>
     */
    private readonly array $entries;

    public function __construct(RerankingEntry ...$entries)
    {
        $this->entries = $entries;
    }

    /**
     * @return list<RerankingEntry>
     */
    public function getContent(): array
    {
        return $this->entries;
    }
}

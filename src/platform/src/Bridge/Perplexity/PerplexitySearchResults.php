<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity;

use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PerplexitySearchResults implements DeltaInterface
{
    /**
     * @param list<array<string, mixed>> $searchResults
     */
    public function __construct(
        private readonly array $searchResults,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSearchResults(): array
    {
        return $this->searchResults;
    }
}

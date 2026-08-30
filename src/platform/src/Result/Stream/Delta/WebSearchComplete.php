<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream\Delta;

use Symfony\AI\Platform\Result\WebSearchResult;

/**
 * Signals that provider-hosted web search output is ready for replay.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class WebSearchComplete implements DeltaInterface
{
    public function __construct(
        private readonly WebSearchResult $webSearch,
    ) {
    }

    public function getWebSearch(): WebSearchResult
    {
        return $this->webSearch;
    }
}

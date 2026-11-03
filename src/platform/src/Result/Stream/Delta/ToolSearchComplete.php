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

use Symfony\AI\Platform\Result\ToolSearchResult;

/**
 * Signals that provider-hosted tool search output is ready for replay.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolSearchComplete implements DeltaInterface
{
    public function __construct(
        private readonly ToolSearchResult $toolSearch,
    ) {
    }

    public function getToolSearch(): ToolSearchResult
    {
        return $this->toolSearch;
    }
}

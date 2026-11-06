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

use Symfony\AI\Platform\Result\ServerToolCallResult;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ServerToolCallComplete implements DeltaInterface
{
    public function __construct(
        private readonly ServerToolCallResult $serverToolCall,
    ) {
    }

    public function getServerToolCall(): ServerToolCallResult
    {
        return $this->serverToolCall;
    }
}

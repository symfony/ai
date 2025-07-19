<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class StreamResult extends BaseResult
{
    public function __construct(
        private readonly \Generator $generator,
        private readonly \Closure $handleToolCallsCallback,
    ) {
    }

    public function getContent(): \Generator
    {
        $streamedResult = '';
        foreach ($this->generator as $value) {
            if ($value instanceof ToolCallResult) {
                yield from ($this->handleToolCallsCallback)($value, Message::ofAssistant($streamedResult))->getContent();

                break;
            }

            $streamedResult .= $value;

            yield $value;
        }
    }
}

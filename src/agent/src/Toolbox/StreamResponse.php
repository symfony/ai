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
use Symfony\AI\Platform\Response\BaseResponse;
use Symfony\AI\Platform\Response\ToolCallResponse;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class StreamResponse extends BaseResponse
{
    public function __construct(
        private readonly \Generator $generator,
        private readonly \Closure $handleToolCallsCallback,
    ) {
    }

    public function getContent(): \Generator
    {
        $streamedResponse = '';
        foreach ($this->generator as $value) {
            if ($value instanceof ToolCallResponse) {
                yield from ($this->handleToolCallsCallback)($value, Message::ofAssistant($streamedResponse))->getContent();

                break;
            }

            $streamedResponse .= $value;
            yield $value;
        }
    }
}

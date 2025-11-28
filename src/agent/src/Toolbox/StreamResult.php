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
use Symfony\AI\Platform\Result\StreamResult as PlatformStreamResult;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class StreamResult extends BaseResult
{
    public function __construct(
        private readonly PlatformStreamResult $sourceStreamResult,
        private readonly \Closure $handleToolCallsCallback,
    ) {
    }

    public function getContent(): \Generator
    {
        $streamedResult = '';
        foreach ($this->sourceStreamResult->getContent() as $value) {
            if ($value instanceof ToolCallResult) {
                $innerResult = ($this->handleToolCallsCallback)($value, Message::ofAssistant($streamedResult));

                // Propagate metadata from inner result to this result
                foreach ($innerResult->getMetadata()->all() as $key => $metadataValue) {
                    $this->getMetadata()->add($key, $metadataValue);
                }

                $content = $innerResult->getContent();
                // Strings are iterable in PHP but yield from would iterate character-by-character.
                // We need to yield the complete string as a single value to preserve streaming behavior.
                // null should also be yielded as-is.
                if (\is_string($content) || null === $content || !is_iterable($content)) {
                    yield $content;
                } else {
                    yield from $content;
                }

                if ($innerResult->getMetadata()->has('calls')) {
                    $innerCalls = $innerResult->getMetadata()->get('calls');
                    $previousCalls = $this->getMetadata()->get('calls', []);
                    $calls = array_merge($previousCalls, $innerCalls);
                } else {
                    $calls[] = $innerResult->getMetadata()->all();
                }

                if ($calls !== ['calls' => []]) {
                    $this->getMetadata()->add('calls', $calls);
                }

                continue;
            }

            $streamedResult .= $value;

            yield $value;

        }

        // Attach the metadata from the platform stream to the agent after the stream has been fully processed
        // and the post-result metadata, such as usage, has been received.
        $calls = $this->getMetadata()->get('calls', []);
        $calls[] = $this->sourceStreamResult->getMetadata()->all();
        $this->getMetadata()->add('calls', $calls);
    }
}

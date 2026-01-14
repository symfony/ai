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
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\StreamResult as PlatformStreamResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class StreamResult extends BaseResult
{
    public function __construct(
        private readonly PlatformStreamResult $streamResult,
        private readonly \Closure $handleToolCallsCallback,
    ) {
    }

    public function getContent(): \Generator
    {
        $streamedResult = '';
        foreach ($this->streamResult->getContent() as $value) {
            if ($value instanceof ToolCallResult) {
                $innerResult = ($this->handleToolCallsCallback)($value, Message::ofAssistant($streamedResult));

                $content = $innerResult->getContent();
                // Strings are iterable in PHP but yield from would iterate character-by-character.
                // We need to yield the complete string as a single value to preserve streaming behavior.
                // null should also be yielded as-is.
                if (\is_string($content) || null === $content || !is_iterable($content)) {
                    yield $content;
                } else {
                    yield from $content;
                }

                // Propagate metadata from inner result to this result
                $this->propagateMetadata($innerResult->getMetadata());

                break;
            }

            $streamedResult .= $value;

            yield $value;
        }

        $this->propagateMetadata($this->streamResult->getMetadata());
    }

    private function propagateMetadata(Metadata $source): void
    {
        foreach ($source->all() as $key => $value) {
            if ('token_usage' === $key && $this->getMetadata()->get('token_usage') instanceof TokenUsageInterface && $value instanceof TokenUsageInterface) {
                $this->getMetadata()->add('token_usage', new TokenUsageAggregation($this->getMetadata()->get('token_usage'), $value));
                continue;
            }

            $this->getMetadata()->add($key, $value);
        }
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Result;

use Symfony\AI\Agent\Toolbox\StreamResult as ToolboxStreamResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Marco van Angeren <marco@jouwweb.nl>
 */
final class AccumulatingStreamResult
{
    private ?\Closure $onComplete = null;

    public function __construct(
        private readonly StreamResult|ToolboxStreamResult $innerResult,
        ?\Closure $onComplete = null,
    ) {
        $this->onComplete = $onComplete;
    }

    public function addOnComplete(\Closure $callback): void
    {
        $existingCallback = $this->onComplete;

        $this->onComplete = $existingCallback
            ? function (AssistantMessage $message) use ($existingCallback, $callback) {
                $existingCallback($message);
                $callback($message);
            }
        : $callback;
    }

    public function getContent(): \Generator
    {
        $accumulatedContent = '';
        $toolCalls = [];

        try {
            foreach ($this->innerResult->getContent() as $value) {
                if ($value instanceof ToolCallResult) {
                    array_push($toolCalls, ...$value->getContent());
                    yield $value;
                    continue;
                }

                $accumulatedContent .= $value;
                yield $value;
            }
        } finally {
            if (null !== $this->onComplete) {
                $assistantMessage = Message::ofAssistant(
                    '' === $accumulatedContent ? null : $accumulatedContent,
                    $toolCalls ?: null
                );

                $assistantMessage->getMetadata()->set($this->innerResult->getMetadata()->all());

                ($this->onComplete)($assistantMessage);
            }
        }
    }

    public function getMetadata(): Metadata
    {
        return $this->innerResult->getMetadata();
    }
}

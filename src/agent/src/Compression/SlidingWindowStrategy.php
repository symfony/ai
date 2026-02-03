<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Compression;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * Keeps only the most recent messages, discarding older ones.
 *
 * This is the simplest compression strategy - it maintains a sliding window
 * of the N most recent messages. The system message is always preserved.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SlidingWindowStrategy implements CompressionStrategyInterface
{
    /**
     * @param int $max       Maximum number of messages to keep (excluding system message)
     * @param int $threshold Number of messages that triggers compression
     */
    public function __construct(
        private readonly int $max = 10,
        private readonly int $threshold = 20,
    ) {
    }

    public function shouldCompress(MessageBag $messages): bool
    {
        return $this->threshold < \count($messages->withoutSystemMessage());
    }

    public function compress(MessageBag $messages): MessageBag
    {
        $systemMessage = $messages->getSystemMessage();
        $nonSystemMessages = $messages->withoutSystemMessage()->getMessages();

        $recentMessages = \array_slice($nonSystemMessages, -$this->max);

        if (null !== $systemMessage) {
            return new MessageBag($systemMessage, ...$recentMessages);
        }

        return new MessageBag(...$recentMessages);
    }
}

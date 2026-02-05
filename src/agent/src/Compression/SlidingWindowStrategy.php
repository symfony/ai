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
use Symfony\AI\Platform\Message\SystemMessage;

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
     * @param int $maxMessages Maximum number of messages to keep (excluding system message)
     * @param int $threshold   Number of messages that triggers compression
     */
    public function __construct(
        private readonly int $maxMessages = 10,
        private readonly int $threshold = 20,
    ) {
    }

    public function shouldCompress(MessageBag $messages): bool
    {
        // Count non-system messages
        $count = 0;
        foreach ($messages as $message) {
            if (!$message instanceof SystemMessage) {
                ++$count;
            }
        }

        return $count > $this->threshold;
    }

    public function compress(MessageBag $messages): MessageBag
    {
        $systemMessage = $messages->getSystemMessage();
        $nonSystemMessages = $messages->withoutSystemMessage()->getMessages();

        $recentMessages = \array_slice($nonSystemMessages, -$this->maxMessages);

        if (null !== $systemMessage) {
            return new MessageBag($systemMessage, ...$recentMessages);
        }

        return new MessageBag(...$recentMessages);
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Compression\Event;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * Event dispatched after history compression is applied.
 * Listeners can inspect or modify the compressed messages before they are used.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AfterContextCompression
{
    public function __construct(
        private readonly MessageBag $originalMessages,
        private MessageBag $compressedMessages,
    ) {
    }

    public function getOriginalMessages(): MessageBag
    {
        return $this->originalMessages;
    }

    public function getCompressedMessages(): MessageBag
    {
        return $this->compressedMessages;
    }

    /**
     * Replace the compressed messages (e.g., for further modification).
     */
    public function setCompressedMessages(MessageBag $messages): void
    {
        $this->compressedMessages = $messages;
    }

    /**
     * Returns the number of messages removed by compression.
     */
    public function getCompressionDelta(): int
    {
        return \count($this->originalMessages) - \count($this->compressedMessages);
    }
}

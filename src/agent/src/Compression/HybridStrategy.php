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
 * Combines multiple compression strategies with progressive thresholds.
 *
 * This strategy applies different compression techniques based on conversation length:
 * - Below soft threshold: no compression
 * - Above soft threshold: use primary strategy (e.g., sliding window)
 * - Above hard threshold: use secondary strategy (e.g., summarization)
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HybridStrategy implements CompressionStrategyInterface
{
    /**
     * @param int $softThreshold Threshold for primary strategy
     * @param int $hardThreshold Threshold for secondary strategy
     */
    public function __construct(
        private readonly CompressionStrategyInterface $primaryStrategy,
        private readonly CompressionStrategyInterface $secondaryStrategy,
        private readonly int $softThreshold = 15,
        private readonly int $hardThreshold = 30,
    ) {
    }

    public function shouldCompress(MessageBag $messages): bool
    {
        return $this->countMessages($messages) > $this->softThreshold;
    }

    public function compress(MessageBag $messages): MessageBag
    {
        $count = $this->countMessages($messages);

        if ($count > $this->hardThreshold) {
            return $this->secondaryStrategy->compress($messages);
        }

        return $this->primaryStrategy->compress($messages);
    }

    private function countMessages(MessageBag $messages): int
    {
        $count = 0;
        foreach ($messages as $message) {
            if (!$message instanceof SystemMessage) {
                ++$count;
            }
        }

        return $count;
    }
}

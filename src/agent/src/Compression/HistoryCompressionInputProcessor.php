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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

/**
 * Input processor that compresses conversation history when it exceeds thresholds.
 *
 * This processor checks the conversation length before each request and applies
 * the configured compression strategy if needed. It can be disabled per-request
 * via the 'compress_history' option.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HistoryCompressionInputProcessor implements InputProcessorInterface
{
    public function __construct(
        private readonly CompressionStrategyInterface $strategy,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function processInput(Input $input): void
    {
        $options = $input->getOptions();

        // Allow disabling compression per-request
        $compressHistory = $options['compress_history'] ?? true;
        unset($options['compress_history']);
        $input->setOptions($options);

        if (false === $compressHistory) {
            return;
        }

        $messages = $input->getMessageBag();

        if (!$this->strategy->shouldCompress($messages)) {
            return;
        }

        // Dispatch before event
        $beforeEvent = new BeforeHistoryCompression($messages);
        $this->eventDispatcher?->dispatch($beforeEvent);

        if ($beforeEvent->isSkipped()) {
            return;
        }

        $compressed = $this->strategy->compress($messages);

        $afterEvent = new AfterHistoryCompression($messages, $compressed);
        $this->eventDispatcher?->dispatch($afterEvent);

        $input->setMessageBag($afterEvent->getCompressedMessages());
    }
}

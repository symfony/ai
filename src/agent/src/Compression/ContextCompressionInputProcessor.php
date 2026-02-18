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
use Symfony\AI\Agent\Compression\Event\AfterContextCompression;
use Symfony\AI\Agent\Compression\Event\BeforeContextCompression;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

/**
 * Input processor that compresses conversation history when it exceeds thresholds.
 *
 * This processor checks the conversation length before each request and applies
 * the configured compression strategy if needed. It can be disabled per-request
 * via the 'compression' option.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ContextCompressionInputProcessor implements InputProcessorInterface
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
        $compressContext = $options['compression'] ?? true;
        unset($options['compression']);
        $input->setOptions($options);

        if (false === $compressContext) {
            return;
        }

        $messages = $input->getMessageBag();

        if (!$this->strategy->shouldCompress($messages)) {
            return;
        }

        $beforeEvent = new BeforeContextCompression($messages);
        $this->eventDispatcher?->dispatch($beforeEvent);

        if ($beforeEvent->isSkipped()) {
            return;
        }

        $compressed = $this->strategy->compress($messages);

        $afterEvent = new AfterContextCompression($messages, $compressed);
        $this->eventDispatcher?->dispatch($afterEvent);

        $input->setMessageBag($afterEvent->getCompressedMessages());
    }
}

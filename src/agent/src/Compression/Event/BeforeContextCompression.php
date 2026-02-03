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
 * Event dispatched before history compression is applied.
 * Listeners can inspect the original messages and optionally skip compression.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BeforeContextCompression
{
    private bool $skip = false;

    public function __construct(
        private readonly MessageBag $originalMessages,
    ) {
    }

    public function getOriginalMessages(): MessageBag
    {
        return $this->originalMessages;
    }

    /**
     * Call this to prevent compression from being applied.
     */
    public function skip(): void
    {
        $this->skip = true;
    }

    public function isSkipped(): bool
    {
        return $this->skip;
    }
}

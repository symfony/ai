<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream;

use Symfony\AI\Platform\Metadata\MetadataAwareInterface;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\AI\Platform\Result\StreamResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class Event implements MetadataAwareInterface
{
    use MetadataAwareTrait;

    private bool $stopRequested = false;

    public function __construct(
        private readonly StreamResult $result,
    ) {
    }

    public function getResult(): StreamResult
    {
        return $this->result;
    }

    /**
     * Signals to the StreamResult that iteration should stop after the current
     * listener pass. The underlying StreamResult will be cancelled and the
     * generator will return cleanly on the next boundary.
     *
     * No-op when invoked from a `CompleteEvent` listener, since iteration has
     * already terminated by that point.
     */
    public function stop(): void
    {
        $this->stopRequested = true;
    }

    public function isStopRequested(): bool
    {
        return $this->stopRequested;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Exception;

/**
 * Thrown when the result of a canceled agent execution is read.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CancellationException extends RuntimeException
{
    public function __construct(
        private readonly ?string $reason = null,
    ) {
        parent::__construct('The agent execution was canceled.');
    }

    public function hasReason(): bool
    {
        return null !== $this->reason;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}

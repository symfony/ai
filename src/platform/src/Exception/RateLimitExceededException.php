<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

/**
 * @author Floran Pagliai <floran.pagliai@gmail.com>
 */
final class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct('Rate limit exceeded.');
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * Thrown internally by {@see CachePlatform} when a result cannot be normalized into a cacheable
 * representation (e.g. an unsupported result type), carrying the live {@see DeferredResult} so the
 * request keeps being served instead of breaking.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class UncacheableResultException extends InvalidArgumentException
{
    public function __construct(
        private readonly DeferredResult $deferredResult,
        \Throwable $previous,
    ) {
        parent::__construct('The result cannot be cached.', 0, $previous);
    }

    public function getDeferredResult(): DeferredResult
    {
        return $this->deferredResult;
    }
}

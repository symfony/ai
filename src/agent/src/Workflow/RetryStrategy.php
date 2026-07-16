<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

/**
 * Backoff strategy used by {@see Executor\RetryExecutor}.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
enum RetryStrategy: string
{
    /**
     * Wait the same base delay between every attempt.
     */
    case Fixed = 'fixed';

    /**
     * Double the base delay after each failed attempt.
     */
    case Exponential = 'exponential';
}

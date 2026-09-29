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
 * Marks a failure that was not caused by the request itself: a transient error on the provider's
 * side, or throttling. Retrying the same request may succeed, so a consumer wiring platform calls
 * into a queue with a retry policy can tell these apart from a permanent failure (bad credentials,
 * a rejected request, filtered content) without enumerating every exception class itself.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface RetryableExceptionInterface extends ExceptionInterface
{
}

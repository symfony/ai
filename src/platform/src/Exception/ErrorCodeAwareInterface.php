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
 * An exception carrying a machine readable code that stays the same across providers.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface ErrorCodeAwareInterface
{
    /**
     * @return non-empty-string
     */
    public function getErrorCode(): string;

    /**
     * Whether repeating the same request may succeed.
     */
    public function isRetryable(): bool;
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Model;

use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Wrapper around Symfony's Profile class with context tracking for multi-directory support.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
class ProfileData
{
    public function __construct(
        public readonly Profile $profile,
        public readonly ?string $context = null,
    ) {
    }
}

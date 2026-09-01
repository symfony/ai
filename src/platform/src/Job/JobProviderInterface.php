<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Job;

/**
 * Implemented by providers whose backend can run work asynchronously.
 *
 * Kept separate from `ProviderInterface` on purpose: most providers answer synchronously and should
 * not have to care about jobs, so this stays an optional capability discovered via `instanceof`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface JobProviderInterface
{
    /**
     * Returns the client resolving this provider's jobs, or null when it has none.
     */
    public function getJobClient(): ?JobClientInterface;
}

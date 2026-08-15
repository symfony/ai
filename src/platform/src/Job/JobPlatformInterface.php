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

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Implemented by platforms that can resolve the asynchronous jobs their providers hand out.
 *
 * Kept out of `PlatformInterface` so a platform that only answers synchronously does not have to
 * care - but every decorator around a platform has to forward it, or a job handle obtained through
 * the decorated platform could not be resolved through it again.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface JobPlatformInterface
{
    /**
     * Returns the job client of the provider the given handle belongs to.
     *
     * @throws InvalidArgumentException when the handle names no provider, or names one this platform
     *                                  does not have or that cannot run jobs
     */
    public function getJobClient(JobHandle $handle): JobClientInterface;
}

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
 * The lifecycle of an asynchronous job, normalized across providers.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
enum JobStateCase: string
{
    /**
     * Accepted by the provider, not started yet.
     */
    case QUEUED = 'queued';

    /**
     * Being worked on.
     */
    case RUNNING = 'running';

    /**
     * Finished, the result can be fetched.
     */
    case SUCCEEDED = 'succeeded';

    /**
     * Finished without a result.
     */
    case FAILED = 'failed';

    /**
     * The provider dropped the job before it was fetched.
     */
    case EXPIRED = 'expired';

    /**
     * Aborted on request.
     */
    case CANCELED = 'canceled';

    /**
     * The provider reported something this enum does not know about. Treated as non-terminal so a
     * runner keeps polling instead of failing on a state a provider added after this was written.
     */
    case UNKNOWN = 'unknown';

    /**
     * Whether the job will not change state anymore.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::SUCCEEDED, self::FAILED, self::EXPIRED, self::CANCELED => true,
            self::QUEUED, self::RUNNING, self::UNKNOWN => false,
        };
    }
}

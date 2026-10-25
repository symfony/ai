<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
enum ExecutionStatus: string
{
    /**
     * The next step is a model round, nothing is running right now.
     */
    case Pending = 'pending';

    /**
     * A step is being executed.
     */
    case Running = 'running';

    /**
     * Tool calls of the model are unanswered, a human or a worker has to answer them.
     */
    case WaitingForInput = 'waiting_for_input';

    case Completed = 'completed';

    case Failed = 'failed';

    case Cancelled = 'cancelled';

    public function isFinished(): bool
    {
        return self::Completed === $this || self::Failed === $this || self::Cancelled === $this;
    }
}

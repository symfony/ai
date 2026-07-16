<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Sleeper;

use Symfony\AI\Agent\Workflow\SleeperInterface;

/**
 * Default sleeper, backed by the native {@see usleep()} function.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class UsleepSleeper implements SleeperInterface
{
    public function sleep(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}

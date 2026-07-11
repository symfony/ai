<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Service;

/**
 * Permission modes for files and directories created by Mate.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class FilePermissions
{
    /**
     * Owner read/write, group read, no access for others. Used for files that may hold secrets
     * or local configuration (mate/.env, mate/config.php, log files).
     */
    public const FILE = 0640;

    /**
     * Owner read/write/execute, group read/execute, no access for others. Used for directories
     * created by Mate so the files they contain are not exposed to other users on shared hosts.
     */
    public const DIRECTORY = 0750;

    /**
     * Owner read/write/execute, group and others read/execute. Used for generated executables
     * such as bin/codex.
     */
    public const EXECUTABLE = 0755;
}

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

use Psr\Log\AbstractLogger;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Logger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $debug = $_SERVER['MATE_DEBUG'] ?? false;

        if (!$debug && 'debug' === $level) {
            return;
        }

        $levelString = match (true) {
            $level instanceof \Stringable => (string) $level,
            \is_string($level) => $level,
            default => 'unknown',
        };

        $logMessage = \sprintf(
            "[%s] %s %s\n",
            strtoupper($levelString),
            $message,
            ([] === $context || !$debug) ? '' : json_encode($context),
        );

        if (($_SERVER['MATE_FILE_LOG'] ?? false) || !\defined('STDERR')) {
            file_put_contents('dev.log', $logMessage, \FILE_APPEND);
        } else {
            fwrite(\STDERR, $logMessage);
        }
    }
}

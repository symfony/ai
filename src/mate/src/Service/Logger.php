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
use Symfony\AI\Mate\Exception\FileWriteException;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Logger extends AbstractLogger
{
    public function __construct(
        private string $logFile = 'dev.log',
        private bool $fileLogEnabled = false,
        private bool $debugEnabled = false,
    ) {
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if (!$this->debugEnabled && 'debug' === $level) {
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
            ([] === $context || !$this->debugEnabled) ? '' : json_encode($context),
        );

        if ($this->fileLogEnabled || !\defined('STDERR')) {
            $result = @file_put_contents($this->logFile, $logMessage, \FILE_APPEND);
            if (false === $result) {
                // Fallback to stderr to ensure message is not lost
                if (\defined('STDERR')) {
                    fwrite(\STDERR, $logMessage);
                }

                throw FileWriteException::forLogFile($this->logFile);
            }
        } else {
            fwrite(\STDERR, $logMessage);
        }
    }
}

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
            ([] === $context || !$this->debugEnabled) ? '' : json_encode($this->normalizeContext($context)),
        );

        if ($this->fileLogEnabled || !\defined('STDERR')) {
            $this->ensureDirectoryExists($this->logFile);

            $result = @file_put_contents($this->logFile, $logMessage, \FILE_APPEND);
            if (false === $result) {
                $errorMessage = \sprintf('Failed to write to log file: "%s"', $this->logFile);
                // Fallback to stderr to ensure message is not lost
                if (\defined('STDERR')) {
                    fwrite(\STDERR, "[ERROR] {$errorMessage}\n");
                    fwrite(\STDERR, $logMessage);
                }

                throw new FileWriteException($errorMessage);
            }
        } else {
            fwrite(\STDERR, $logMessage);
        }
    }

    /**
     * Normalize context data to be JSON-serializable.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \Throwable) {
            return [
                'class' => $value::class,
                'message' => $value->getMessage(),
                'code' => $value->getCode(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
                'trace' => $this->debugEnabled ? $value->getTraceAsString() : null,
            ];
        }

        if (\is_array($value)) {
            return array_map(fn ($item) => $this->normalizeValue($item), $value);
        }

        if (\is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return ['object' => $value::class];
        }

        return $value;
    }

    private function ensureDirectoryExists(string $filePath): void
    {
        $directory = \dirname($filePath);

        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new FileWriteException(\sprintf('Failed to create log directory: "%s"', $directory));
            }
        }
    }
}

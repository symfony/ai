<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Transport;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\Process\Process;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\Acp\Exception\CliNotFoundException;
use Symfony\AI\Platform\Bridge\Acp\Exception\TransportException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * ACP stdio transport using Amp\Process.
 */
final class ProcessTransport implements TransportInterface
{
    private ?Process $process = null;
    private ?WritableResourceStream $stdin = null;
    private ?ReadableResourceStream $stdout = null;
    private string $stdoutBuffer = '';
    private bool $running = false;

    /**
     * @param array<string, string> $environment
     */
    public function __construct(
        private readonly string $command,
        private readonly ?string $workingDirectory = null,
        private readonly array $environment = [],
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function start(): void
    {
        if ($this->running) {
            return;
        }

        $command = $this->buildCommand();
        $this->logger->info('Starting ACP process.', ['command' => $command]);

        $this->process = Process::start($command, $this->workingDirectory, $this->environment);
        $this->stdin = $this->process->getStdin();
        $this->stdout = $this->process->getStdout();
        $this->running = true;
    }

    public function send(array $message): void
    {
        if (!$this->running || null === $this->stdin) {
            throw new TransportException('ACP process stdin is not available.');
        }

        try {
            $payload = json_encode($message, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
            $this->stdin->write($payload);
            $this->logger->debug('ACP request sent.', ['payload' => $message]);
        } catch (\Throwable $e) {
            throw new TransportException('Failed to write to ACP process.', 0, $e);
        }
    }

    public function readNextMessage(): array
    {
        if (!$this->running || null === $this->stdout) {
            throw new TransportException('ACP process stdout is not available.');
        }

        while (true) {
            if (str_contains($this->stdoutBuffer, "\n")) {
                $parts = explode("\n", $this->stdoutBuffer, 2);
                $line = trim($parts[0]);
                $this->stdoutBuffer = $parts[1];

                if ('' === $line) {
                    continue;
                }

                $decoded = json_decode($line, true);
                if (!\is_array($decoded)) {
                    throw new TransportException('ACP returned malformed JSON.');
                }

                $this->logger->debug('ACP response received.', ['payload' => $decoded]);

                return $decoded;
            }

            $chunk = $this->stdout->read();
            if (null === $chunk) {
                throw new TransportException('ACP process closed stdout unexpectedly.');
            }

            $this->stdoutBuffer .= $chunk;
        }
    }

    public function close(): void
    {
        if (!$this->running) {
            return;
        }

        $this->process->kill();
        $this->process = null;
        $this->stdin = null;
        $this->stdout = null;
        $this->stdoutBuffer = '';
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * @return list<string>
     */
    private function buildCommand(): array
    {
        $parts = preg_split('/\s+/', trim($this->command)) ?: [];
        if ([] === $parts) {
            throw new CliNotFoundException('ACP command cannot be empty.');
        }

        $binary = (new ExecutableFinder())->find($parts[0]);
        if (null === $binary) {
            throw new CliNotFoundException(\sprintf('ACP binary "%s" was not found.', $parts[0]));
        }

        $parts[0] = $binary;

        return $parts;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\Process\Process;

/**
 * Wraps a Symfony Process running the Codex CLI as a RawResultInterface.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RawProcessResult implements RawResultInterface
{
    public function __construct(
        private readonly Process $process,
    ) {
    }

    /**
     * Waits for the process to finish, parses all output lines, and returns the final
     * agent message together with usage data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->process->wait();

        if (!$this->process->isSuccessful()) {
            throw new RuntimeException(\sprintf('Codex CLI process failed: "%s"', $this->process->getErrorOutput()));
        }

        $output = $this->process->getOutput();
        $lastAgentMessage = [];
        $lastError = [];
        $usage = [];

        foreach (explode(\PHP_EOL, $output) as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $decoded = json_decode($line, true);

            if (null === $decoded) {
                continue;
            }

            $type = $decoded['type'] ?? '';

            if ('item.completed' === $type
                && 'agent_message' === ($decoded['item']['type'] ?? '')
            ) {
                $lastAgentMessage = $decoded;
            }

            if ('error' === $type) {
                $lastError = $decoded;
            }

            if ('turn.completed' === $type && isset($decoded['usage'])) {
                $usage = $decoded['usage'];
            }
        }

        if ([] !== $lastError && [] === $lastAgentMessage) {
            return $lastError;
        }

        if ([] !== $usage && [] !== $lastAgentMessage) {
            $lastAgentMessage['usage'] = $usage;
        }

        return $lastAgentMessage;
    }

    /**
     * Polls the process for incremental output, yielding each complete JSON line.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function getDataStream(): \Generator
    {
        $buffer = '';

        while ($this->process->isRunning()) {
            $incrementalOutput = $this->process->getIncrementalOutput();

            if ('' === $incrementalOutput) {
                usleep(10000); // 10ms polling interval
                continue;
            }

            $buffer .= $incrementalOutput;
            $lines = explode(\PHP_EOL, $buffer);

            // Keep the last (potentially incomplete) line in the buffer
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);

                if ('' === $line) {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (null !== $decoded) {
                    yield $decoded;
                }
            }
        }

        // Process remaining output after process finishes
        $buffer .= $this->process->getIncrementalOutput();

        foreach (explode(\PHP_EOL, $buffer) as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $decoded = json_decode($line, true);

            if (null !== $decoded) {
                yield $decoded;
            }
        }

        if (!$this->process->isSuccessful()) {
            throw new RuntimeException(\sprintf('Codex CLI process failed: "%s"', $this->process->getErrorOutput()));
        }
    }

    public function getObject(): Process
    {
        return $this->process;
    }
}

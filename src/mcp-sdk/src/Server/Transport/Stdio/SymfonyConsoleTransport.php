<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server\Transport\Stdio;

use Symfony\AI\McpSdk\Server\TransportInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Heavily inspired by https://jolicode.com/blog/mcp-the-open-protocol-that-turns-llm-chatbots-into-intelligent-agents.
 */
final class SymfonyConsoleTransport implements TransportInterface
{
    private string $buffer = '';

    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
    ) {
    }

    public function initialize(): void
    {
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function receive(): \Generator
    {
        $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() ?? \STDIN : \STDIN;
        $line = fgets($stream);
        if (false === $line) {
            return;
        }
        $this->buffer .= \STDIN === $stream ? rtrim($line).\PHP_EOL : $line;
        if (str_contains($this->buffer, \PHP_EOL)) {
            $lines = explode(\PHP_EOL, $this->buffer);
            $this->buffer = array_pop($lines);

            yield from $lines;
        }
    }

    public function send(string $data): void
    {
        $this->output->writeln($data);
    }

    public function close(): void
    {
    }
}

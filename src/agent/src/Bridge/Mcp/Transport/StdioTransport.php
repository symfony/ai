<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp\Transport;

use Symfony\AI\Agent\Bridge\Mcp\Exception\ConnectionException;
use Symfony\AI\Agent\Bridge\Mcp\Exception\McpException;

/**
 * Transport for communicating with MCP servers via stdio.
 *
 * This transport spawns a local process and communicates via stdin/stdout
 * using JSON-RPC messages.
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
final class StdioTransport implements TransportInterface
{
    /** @var resource|null */
    private $process;

    /** @var array<int, resource> */
    private array $pipes = [];

    /**
     * @param string                $command The command to execute (e.g., 'npx', 'php', 'python')
     * @param list<string>          $args    Command arguments (e.g., ['@modelcontextprotocol/server-filesystem', '/tmp'])
     * @param array<string, string> $env     Additional environment variables
     */
    public function __construct(
        private readonly string $command,
        private readonly array $args = [],
        private readonly array $env = [],
        private readonly int $timeout = 30,
    ) {
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function connect(): void
    {
        if (null !== $this->process) {
            return;
        }

        $commandLine = $this->command;
        if ([] !== $this->args) {
            $commandLine .= ' '.implode(' ', array_map('escapeshellarg', $this->args));
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $env = array_merge(getenv(), $this->env);

        $this->process = proc_open($commandLine, $descriptorSpec, $this->pipes, null, $env);

        if (!\is_resource($this->process)) {
            throw new ConnectionException(\sprintf('Failed to start MCP server process: "%s".', $commandLine));
        }

        // Set stdout to non-blocking for reading
        stream_set_blocking($this->pipes[1], false);
    }

    public function request(array $data): array
    {
        $this->send($data);

        return $this->receive();
    }

    public function notify(array $data): void
    {
        $this->send($data);
    }

    public function disconnect(): void
    {
        if (null === $this->process) {
            return;
        }

        // Close pipes
        foreach ($this->pipes as $pipe) {
            if (\is_resource($pipe)) {
                fclose($pipe);
            }
        }

        // Terminate process
        if (\is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }

        $this->process = null;
        $this->pipes = [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function send(array $data): void
    {
        if (null === $this->process || !\is_resource($this->process)) {
            throw new McpException('Cannot send data: MCP server process is not running.');
        }

        $json = json_encode($data, \JSON_THROW_ON_ERROR);
        $written = fwrite($this->pipes[0], $json."\n");

        if (false === $written || $written !== \strlen($json) + 1) {
            throw new McpException('Failed to write data to MCP server process.');
        }

        fflush($this->pipes[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function receive(): array
    {
        if (null === $this->process || !\is_resource($this->process)) {
            throw new McpException('Cannot receive data: MCP server process is not running.');
        }

        $buffer = '';
        $startTime = time();

        while (true) {
            // Check timeout
            if ((time() - $startTime) >= $this->timeout) {
                $stderr = stream_get_contents($this->pipes[2]);
                $errorMsg = '' !== $stderr ? \sprintf(' Stderr: %s', $stderr) : '';

                throw new McpException(\sprintf('Timeout waiting for MCP server response.%s', $errorMsg));
            }

            // Wait for data using stream_select (1 second timeout per iteration)
            $read = [$this->pipes[1]];
            $write = $except = null;
            $ready = stream_select($read, $write, $except, 1);

            if (false === $ready) {
                throw new McpException('stream_select() failed while waiting for MCP server response.');
            }

            if (0 === $ready) {
                // Timeout on this iteration, continue waiting
                continue;
            }

            $chunk = fread($this->pipes[1], 4096);

            if (false !== $chunk && '' !== $chunk) {
                $buffer .= $chunk;

                // MCP sends one JSON message per line
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines); // Keep incomplete line in buffer

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ('' === $line) {
                        continue;
                    }

                    $decoded = json_decode($line, true);
                    if (null !== $decoded) {
                        // Skip notifications (no 'id' field) - we want responses
                        if (isset($decoded['id'])) {
                            return $decoded;
                        }
                        // Notification received, continue waiting for response
                    }
                }
            }
        }
    }
}

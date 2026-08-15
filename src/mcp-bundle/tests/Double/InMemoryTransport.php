<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Double;

use Mcp\Client\Transport\BaseTransport;
use Mcp\Exception\ConnectionException;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;

/**
 * Answers every request in-process from canned results, so a real Mcp\Client — including its
 * initialize handshake — can be exercised without a child process or an HTTP round trip.
 *
 * Each response is fed back through handleMessage() while send() is still running, which makes
 * Protocol::request() find it via consumeResponse() and return without ever suspending the fiber.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryTransport extends BaseTransport
{
    public int $connectCount = 0;
    public int $closeCount = 0;

    /** @var list<array<string, mixed>> */
    public array $sent = [];

    /**
     * @param array<string, list<array<string, mixed>>> $results  method => the result of each successive call
     * @param array<string, string>                     $failures method => JSON-RPC error message
     */
    public function __construct(
        private array $results = [],
        private readonly array $failures = [],
        private readonly bool $failOnConnect = false,
    ) {
        parent::__construct();
    }

    public function connect(): void
    {
        ++$this->connectCount;

        if ($this->failOnConnect) {
            throw new ConnectionException('Initialization failed: the double refuses to connect.');
        }

        $fiber = new \Fiber(fn () => $this->handleInitialize());
        $fiber->start();

        $result = $fiber->getReturn();
        if ($result instanceof Error) {
            throw new ConnectionException('Initialization failed: '.$result->message);
        }
    }

    public function send(string $data): void
    {
        /** @var array<string, mixed> $message */
        $message = json_decode($data, true, 512, \JSON_THROW_ON_ERROR);
        $this->sent[] = $message;

        // Notifications carry no id and expect no answer.
        if (!isset($message['id'])) {
            return;
        }

        $method = (string) ($message['method'] ?? '');

        if (isset($this->failures[$method])) {
            $this->handleMessage(json_encode([
                'jsonrpc' => '2.0',
                'id' => $message['id'],
                'error' => ['code' => -32000, 'message' => $this->failures[$method]],
            ], \JSON_THROW_ON_ERROR));

            return;
        }

        $this->handleMessage(json_encode([
            'jsonrpc' => '2.0',
            'id' => $message['id'],
            'result' => $this->nextResult($method),
        ], \JSON_THROW_ON_ERROR));
    }

    public function runRequest(\Fiber $fiber, ?callable $onProgress = null): Response|Error
    {
        $fiber->start();

        return $fiber->getReturn();
    }

    public function close(): void
    {
        ++$this->closeCount;
    }

    /**
     * @return array<string, mixed>
     */
    private function nextResult(string $method): array
    {
        if ('initialize' === $method && !isset($this->results['initialize'])) {
            return [
                'protocolVersion' => '2025-11-25',
                'capabilities' => [],
                'serverInfo' => ['name' => 'test-server', 'version' => '1.0.0'],
                'instructions' => 'Be nice.',
            ];
        }

        $results = $this->results[$method] ?? [];
        if ([] === $results) {
            // The list results require their collection key to be present, even when empty.
            return match ($method) {
                'tools/list' => ['tools' => []],
                'prompts/list' => ['prompts' => []],
                'resources/list' => ['resources' => []],
                'resources/templates/list' => ['resourceTemplates' => []],
                default => [],
            };
        }

        // Successive calls walk the list, so pagination can be scripted; the last entry repeats.
        $next = array_shift($results);
        if ([] !== $results) {
            $this->results[$method] = $results;
        }

        return $next;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Mcp;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Wire\McpHeader;
use Mcp\Server\Builder;
use Mcp\Server\Stateless\RequestMeta;
use Mcp\Server\Stateless\StatelessProtocol;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;
use Symfony\AI\Agent\Bridge\Mcp\ToolsetInterface;
use Symfony\AI\AiBundle\Exception\RuntimeException;

/**
 * The tools of an MCP server this application exposes itself, called in-process.
 *
 * An agent uses exactly the tools external MCP clients see, without a transport: no child
 * process and no HTTP request back into the application. Calls go through the server's own
 * stateless protocol, so schema validation, result formatting and tool errors behave as they
 * do for a remote client.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class LocalServerToolset implements ToolsetInterface
{
    private ?StatelessProtocol $protocol = null;
    private int $requestId = 0;

    public function __construct(
        private readonly string $name,
        private readonly Builder $builder,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTools(): array
    {
        $tools = [];
        $cursor = null;

        do {
            try {
                $page = ListToolsResult::fromArray($this->request('tools/list', null === $cursor ? [] : ['cursor' => $cursor]));
            } catch (RuntimeException $e) {
                throw ToolCallException::listFailed($this->name, $e);
            }

            foreach ($page->tools as $tool) {
                $tools[] = $tool;
            }

            $cursor = $page->nextCursor;
        } while (null !== $cursor);

        return $tools;
    }

    public function callTool(string $name, array $arguments = []): CallToolResult
    {
        try {
            return CallToolResult::fromArray($this->request('tools/call', ['name' => $name, 'arguments' => (object) $arguments]));
        } catch (RuntimeException $e) {
            throw ToolCallException::callFailed($this->name, $name, $e);
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function request(string $method, array $params): array
    {
        $this->protocol ??= $this->builder->buildStateless();

        $version = ProtocolVersion::V2026_07_28->value;
        $params['_meta'] = [
            RequestMeta::PROTOCOL_VERSION => $version,
            RequestMeta::CLIENT_CAPABILITIES => new \stdClass(),
        ];

        // The stateless protocol checks the headers an HTTP client sends against the body.
        $headers = [
            McpHeader::PROTOCOL_VERSION => $version,
            McpHeader::METHOD => $method,
        ];
        if (isset($params['name'])) {
            $headers[McpHeader::NAME] = $params['name'];
        }

        $result = $this->protocol->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => ++$this->requestId,
            'method' => $method,
            'params' => $params,
        ], \JSON_THROW_ON_ERROR), $headers);

        $message = json_decode($result->toJson(), true, flags: \JSON_THROW_ON_ERROR);

        if (\is_array($message) && \is_array($message['error'] ?? null)) {
            throw new RuntimeException(\sprintf('%s (%s)', json_encode($message['error']['message'] ?? null), json_encode($message['error']['code'] ?? null)));
        }

        if (!\is_array($message) || !\is_array($message['result'] ?? null)) {
            throw new RuntimeException(\sprintf('The server answered "%s" without a result.', $method));
        }

        return $message['result'];
    }
}

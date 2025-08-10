<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Message;

final readonly class Error implements \JsonSerializable
{
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;
    public const PARSE_ERROR = -32700;
    public const RESOURCE_NOT_FOUND = -32002;
    public const REQUEST_TIMEOUT = -32001;

    public function __construct(
        public string|int $id,
        public int $code,
        public string $message,
    ) {
    }

    /**
     * @param array{jsonrpc: string, id: string|int, error: array{code: int, message: string}} $data
     */
    public static function from(array $data): self
    {
        return new self($data['id'], $data['error']['code'], $data['error']['message']);
    }

    public static function invalidRequest(string|int $id, string $message = 'Invalid Request'): self
    {
        return new self($id, self::INVALID_REQUEST, $message);
    }

    public static function methodNotFound(string|int $id, string $message = 'Method not found'): self
    {
        return new self($id, self::METHOD_NOT_FOUND, $message);
    }

    public static function invalidParams(string|int $id, string $message = 'Invalid params'): self
    {
        return new self($id, self::INVALID_PARAMS, $message);
    }

    public static function internalError(string|int $id, string $message = 'Internal error'): self
    {
        return new self($id, self::INTERNAL_ERROR, $message);
    }

    public static function parseError(string|int $id, string $message = 'Parse error'): self
    {
        return new self($id, self::PARSE_ERROR, $message);
    }

    public static function requestTimeout(string|int $id, string $message = 'Request timeout'): self
    {
        return new self($id, self::REQUEST_TIMEOUT, $message);
    }

    /**
     * @return array{
     *     jsonrpc: string,
     *     id: string|int,
     *     error: array{code: int, message: string}
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
            ],
        ];
    }
}

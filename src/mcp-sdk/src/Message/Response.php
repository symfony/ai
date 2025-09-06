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

final readonly class Response implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $result
     */
    public function __construct(
        public string|int $id,
        public array $result = [],
    ) {
    }

    /**
     * @param array{jsonrpc: string, id: string|int, result: array<string, mixed>} $data
     */
    public static function from(array $data): self
    {
        return new self($data['id'], $data['result']);
    }

    /**
     * @return array{jsonrpc: string, id: string|int, result: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'result' => $this->result,
        ];
    }
}

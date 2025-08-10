<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server;

use Symfony\AI\McpSdk\Message\Error;
use Symfony\AI\McpSdk\Message\Response;

final readonly class PendingResponse
{
    /**
     * @param \Closure(Response|Error): void $callback
     */
    public function __construct(
        private string|int $id,
        private \DateTimeImmutable $sentAt,
        private ?\Closure $callback = null,
    ) {
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function resolve(Response|Error $response): void
    {
        if (null !== $this->callback) {
            ($this->callback)($response);
        }
    }
}

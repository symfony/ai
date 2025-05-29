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

namespace PhpLlm\McpSdk\Server;

interface TransportInterface
{
    public function initialize(): void;

    public function isConnected(): bool;

    public function receive(): \Generator;

    public function send(string $data): void;

    public function close(): void;
}

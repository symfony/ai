<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Server;

/**
 * Describes the name and version of an MCP implementation, with an optional title for UI representation.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#implementation
 */
final readonly class Implementation
{
    public function __construct(
        public string $name = 'app',
        public string $version = 'dev',
    ) {
    }
}

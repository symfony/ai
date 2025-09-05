<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Tool;

/**
 * Present if the server offers any tools to call.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-tools
 */
final readonly class ToolCapability
{
    public function __construct(
        /** Whether this server supports notifications for changes to the tool list. */
        public ?bool $listChanged = null,
    ) {
    }
}

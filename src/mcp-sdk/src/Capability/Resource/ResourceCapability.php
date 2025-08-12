<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Resource;

/**
 * Present if the server offers any resources to read.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-resources
 */
final readonly class ResourceCapability
{
    public function __construct(
        /** Whether this server supports notifications for changes to the resource list. */
        public ?bool $subscribe = null,
        /** Whether this server supports subscribing to resource updates. */
        public ?bool $listChanged = null,
    ) {
    }
}

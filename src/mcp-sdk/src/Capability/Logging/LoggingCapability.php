<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Logging;

/**
 * Present if the server supports sending log messages to the client.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-logging
 */
final class LoggingCapability
{
    public function __construct(
    ) {
    }
}

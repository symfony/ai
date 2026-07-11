<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Http;

use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Resolves the middleware stack for the streamable HTTP transport based on the configured DNS
 * rebinding protection: the SDK secure defaults are kept, restricted to custom hosts, or disabled.
 *
 * @author Christopher Hertel
 */
final class MiddlewareFactory
{
    /**
     * @param list<string>|false|null $allowedHosts Hostnames allowed by the DNS rebinding protection: null keeps the
     *                                              SDK default (localhost only), an array restricts it to those hosts,
     *                                              false disables the protection entirely
     */
    public function __construct(
        private readonly array|false|null $allowedHosts = null,
    ) {
    }

    /**
     * Returns null to keep the SDK secure defaults, or a tailored middleware stack when the DNS
     * rebinding protection needs to be disabled or restricted to custom hosts.
     *
     * @return list<MiddlewareInterface>|null
     */
    public function create(): ?array
    {
        // Unset: keep the SDK secure defaults (DNS rebinding protection limited to localhost).
        if (null === $this->allowedHosts) {
            return null;
        }

        $middleware = [];
        foreach (StreamableHttpTransport::defaultMiddleware() as $defaultMiddleware) {
            if ($defaultMiddleware instanceof DnsRebindingProtectionMiddleware) {
                // false: disable the protection entirely to expose a public MCP server.
                if (false === $this->allowedHosts) {
                    continue;
                }

                // array: restrict the protection to the configured hosts.
                $defaultMiddleware = new DnsRebindingProtectionMiddleware($this->allowedHosts);
            }

            $middleware[] = $defaultMiddleware;
        }

        return $middleware;
    }
}

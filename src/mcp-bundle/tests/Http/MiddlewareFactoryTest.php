<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Http;

use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;

class MiddlewareFactoryTest extends TestCase
{
    public function testKeepsSecureDefaultsWhenAllowedHostsIsUnset()
    {
        $this->assertNull((new MiddlewareFactory(null))->create());
    }

    public function testRestrictsDnsRebindingProtectionToConfiguredHosts()
    {
        $middleware = (new MiddlewareFactory(['example.com', 'mcp.example.com']))->create();

        $this->assertIsArray($middleware);

        $dnsMiddleware = null;
        foreach ($middleware as $entry) {
            if ($entry instanceof DnsRebindingProtectionMiddleware) {
                $dnsMiddleware = $entry;
            }
        }

        $this->assertInstanceOf(DnsRebindingProtectionMiddleware::class, $dnsMiddleware);

        $allowedHosts = (new \ReflectionProperty(DnsRebindingProtectionMiddleware::class, 'allowedHosts'))
            ->getValue($dnsMiddleware);
        $this->assertSame(['example.com', 'mcp.example.com'], $allowedHosts);
    }

    public function testRemovesDnsRebindingProtectionWhenAllowedHostsIsFalse()
    {
        $middleware = (new MiddlewareFactory(false))->create();

        $this->assertIsArray($middleware);
        $this->assertNotSame([], $middleware);

        foreach ($middleware as $entry) {
            $this->assertNotInstanceOf(DnsRebindingProtectionMiddleware::class, $entry);
        }
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Controller;

use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Controller\McpController;

class McpControllerTest extends TestCase
{
    public function testKeepsSecureDefaultsWhenAllowedHostsIsUnset()
    {
        $this->assertNull($this->resolveMiddleware(null));
    }

    public function testRestrictsDnsRebindingProtectionToConfiguredHosts()
    {
        $middleware = $this->resolveMiddleware(['example.com', 'mcp.example.com']);

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
        $middleware = $this->resolveMiddleware(false);

        $this->assertIsArray($middleware);
        $this->assertNotSame([], $middleware);

        foreach ($middleware as $entry) {
            $this->assertNotInstanceOf(DnsRebindingProtectionMiddleware::class, $entry);
        }
    }

    /**
     * @param list<string>|false|null $allowedHosts
     *
     * @return list<object>|null
     */
    private function resolveMiddleware(array|false|null $allowedHosts): ?array
    {
        $controller = (new \ReflectionClass(McpController::class))->newInstanceWithoutConstructor();

        (new \ReflectionProperty(McpController::class, 'allowedHosts'))->setValue($controller, $allowedHosts);

        return (new \ReflectionMethod(McpController::class, 'resolveMiddleware'))->invoke($controller);
    }
}

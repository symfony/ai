<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\Component\Routing\Exception\LogicException;

class RouteLoaderTest extends TestCase
{
    public function testDefaultRouteRegistered()
    {
        $loader = new RouteLoader(true, '/_mcp');
        $collection = $loader->load(null, 'mcp');
        $prefix = self::prefix('/_mcp');

        $this->assertCount(1, $collection);
        $route = $collection->get($prefix.'_endpoint');
        $this->assertNotNull($route);
        $this->assertSame('/_mcp', $route->getPath());
    }

    public function testAdditionalRoutesRegistered()
    {
        $additionalRoutes = [
            '/.well-known/oauth-protected-resource',
            '/.well-known/oauth-authorization-server',
            '/authorize',
            '/token',
            '/register',
        ];

        $loader = new RouteLoader(true, '/_mcp', $additionalRoutes);
        $collection = $loader->load(null, 'mcp');
        $prefix = self::prefix('/_mcp');

        $this->assertCount(6, $collection);
        $this->assertNotNull($collection->get($prefix.'_endpoint'));

        $expectedSuffixes = [
            'well_known_oauth_protected_resource',
            'well_known_oauth_authorization_server',
            'authorize',
            'token',
            'register',
        ];

        foreach ($expectedSuffixes as $i => $suffix) {
            $name = $prefix.'_'.$suffix;
            $route = $collection->get($name);
            $this->assertNotNull($route, \sprintf('Route %s should exist', $name));
            $this->assertSame($additionalRoutes[$i], $route->getPath());
            $this->assertSame('mcp.server.controller::handle', $route->getDefault('_controller'));
        }
    }

    public function testDifferentPathsProduceUniqueRouteNames()
    {
        $loader1 = new RouteLoader(true, '/mcp');
        $loader2 = new RouteLoader(true, '/api/mcp');

        $collection1 = $loader1->load(null, 'mcp');
        $names1 = array_keys($collection1->all());

        // Reset loaded state via new instance
        $collection2 = $loader2->load(null, 'mcp');
        $names2 = array_keys($collection2->all());

        $this->assertNotSame($names1[0], $names2[0]);
    }

    public function testHttpDisabledReturnsEmptyCollection()
    {
        $loader = new RouteLoader(false, '/_mcp', ['/authorize']);
        $collection = $loader->load(null, 'mcp');

        $this->assertCount(0, $collection);
    }

    public function testDoubleLoadThrowsException()
    {
        $loader = new RouteLoader(true, '/_mcp');
        $loader->load(null, 'mcp');

        $this->expectException(LogicException::class);
        $loader->load(null, 'mcp');
    }

    public function testSupportsOnlyMcpType()
    {
        $loader = new RouteLoader(true, '/_mcp');

        $this->assertTrue($loader->supports(null, 'mcp'));
        $this->assertFalse($loader->supports(null, 'other'));
    }

    private static function prefix(string $path): string
    {
        return '_mcp_'.substr(hash('xxh3', $path), 0, 6);
    }
}

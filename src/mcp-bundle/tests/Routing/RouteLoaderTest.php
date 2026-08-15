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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\LogicException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RouteLoaderTest extends TestCase
{
    public function testSupportsTheMcpType()
    {
        $loader = new RouteLoader([]);

        $this->assertTrue($loader->supports('.', 'mcp'));
        $this->assertFalse($loader->supports('.', 'yaml'));
    }

    public function testEmitsOneRoutePerServer()
    {
        $loader = new RouteLoader([
            ['name' => 'public', 'path' => '/mcp', 'controller' => 'mcp.server.public.controller::handle'],
            ['name' => 'editors', 'path' => '/mcp/editors', 'controller' => 'mcp.server.editors.controller::handle'],
        ]);

        $collection = $loader->load('.', 'mcp');

        $this->assertCount(2, $collection);
        $this->assertSame(['_mcp_endpoint_public', '_mcp_endpoint_editors'], array_keys($collection->all()));

        $editors = $collection->get('_mcp_endpoint_editors');
        $this->assertSame('/mcp/editors', $editors->getPath());
        $this->assertSame('mcp.server.editors.controller::handle', $editors->getDefault('_controller'));
        $this->assertSame(
            [Request::METHOD_GET, Request::METHOD_POST, Request::METHOD_DELETE, Request::METHOD_OPTIONS],
            $editors->getMethods(),
        );
    }

    public function testEmitsNothingWithoutHttpServers()
    {
        $this->assertCount(0, (new RouteLoader([]))->load('.', 'mcp'));
    }

    public function testRefusesToLoadTwice()
    {
        $loader = new RouteLoader([]);
        $loader->load('.', 'mcp');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Do not add the "mcp" loader twice.');

        $loader->load('.', 'mcp');
    }
}

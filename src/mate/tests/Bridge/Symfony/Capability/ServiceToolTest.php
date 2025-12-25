<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Bridge\Symfony\Capability;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Capability\ServiceTool;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ServiceToolTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/../../../Fixtures/container';
    }

    public function testGetAllServicesReturnsServicesFromContainer()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getAllServices();

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayHasKey('logger', $services);
        $this->assertArrayHasKey('event_dispatcher', $services);

        $this->assertSame('Symfony\Component\Cache\Adapter\FilesystemAdapter', $services['cache.app']);
        $this->assertSame('Psr\Log\NullLogger', $services['logger']);
        $this->assertSame('Symfony\Component\EventDispatcher\EventDispatcher', $services['event_dispatcher']);
    }

    public function testGetAllServicesReturnsEmptyArrayWhenContainerNotFound()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool('/non/existent/directory', $provider);

        $services = $tool->getAllServices();

        $this->assertEmpty($services);
    }

    public function testGetAllServicesIncludesServicesWithMethodCalls()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getAllServices();

        $this->assertArrayHasKey('event_dispatcher', $services);
        $this->assertSame('Symfony\Component\EventDispatcher\EventDispatcher', $services['event_dispatcher']);
    }

    public function testGetAllServicesIncludesServicesWithTags()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getAllServices();

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayHasKey('logger', $services);
    }

    public function testGetAllServicesResolvesAliases()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getAllServices();

        // my_service is an alias to cache.app
        $this->assertArrayHasKey('my_service', $services);
        $this->assertSame('Symfony\Component\Cache\Adapter\FilesystemAdapter', $services['my_service']);
    }

    public function testGetAllServicesStripsLeadingDotsFromServiceIds()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getAllServices();

        // .service_locator.abc123 should be accessible without the leading dot
        $this->assertArrayHasKey('service_locator.abc123', $services);
    }

    public function testGetAllServicesIncludesServicesWithFactory()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getAllServices();

        $this->assertArrayHasKey('router', $services);
        $this->assertSame('Symfony\Component\Routing\Router', $services['router']);
    }
}

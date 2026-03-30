<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Capability;

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
        $this->fixturesDir = \dirname(__DIR__).'/Fixtures';
    }

    public function testGetServicesReturnsServicesFromContainer()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices();

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayHasKey('logger', $services);
        $this->assertArrayHasKey('event_dispatcher', $services);

        $this->assertSame('Symfony\Component\Cache\Adapter\FilesystemAdapter', $services['cache.app']);
        $this->assertSame('Psr\Log\NullLogger', $services['logger']);
        $this->assertSame('Symfony\Component\EventDispatcher\EventDispatcher', $services['event_dispatcher']);
    }

    public function testGetServicesReturnsEmptyArrayWhenContainerNotFound()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool('/non/existent/directory', $provider);

        $services = $tool->getServices();

        $this->assertEmpty($services);
    }

    public function testGetServicesIncludesServicesWithMethodCalls()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices();

        $this->assertArrayHasKey('event_dispatcher', $services);
        $this->assertSame('Symfony\Component\EventDispatcher\EventDispatcher', $services['event_dispatcher']);
    }

    public function testGetServicesIncludesServicesWithTags()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices();

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayHasKey('logger', $services);
    }

    public function testGetServicesResolvesAliases()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices();

        // my_service is an alias to cache.app
        $this->assertArrayHasKey('my_service', $services);
        $this->assertSame('Symfony\Component\Cache\Adapter\FilesystemAdapter', $services['my_service']);
    }

    public function testGetServicesStripsLeadingDotsFromServiceIds()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices();

        // .service_locator.abc123 should be accessible without the leading dot
        $this->assertArrayHasKey('service_locator.abc123', $services);
    }

    public function testGetServicesIncludesServicesWithFactory()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices();

        $this->assertArrayHasKey('router', $services);
        $this->assertSame('Symfony\Component\Routing\Router', $services['router']);
    }

    public function testGetServicesFiltersByQuery()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices('cache');

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayNotHasKey('logger', $services);
        $this->assertArrayNotHasKey('event_dispatcher', $services);
    }

    public function testGetServicesFiltersByClassName()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices('NullLogger');

        $this->assertArrayHasKey('logger', $services);
        $this->assertArrayNotHasKey('cache.app', $services);
    }

    public function testGetServicesFilterIsCaseInsensitive()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices('CACHE');

        $this->assertArrayHasKey('cache.app', $services);
    }

    public function testGetServicesWithEmptyQueryReturnsAll()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $allServices = $tool->getServices();
        $emptyQueryServices = $tool->getServices('');

        $this->assertSame($allServices, $emptyQueryServices);
    }

    public function testGetServicesWithNonMatchingQueryReturnsEmpty()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $tool->getServices('nonexistent_service_xyz');

        $this->assertEmpty($services);
    }

    public function testGetServicesDetectsCustomKernelClassName()
    {
        $tempDir = sys_get_temp_dir().'/symfony_ai_mate_test_'.uniqid();
        $tempFile = $tempDir.'/Custom_AppKernelDevDebugContainer.xml';

        mkdir($tempDir);

        try {
            $xmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<container xmlns="http://symfony.com/schema/dic/services">
    <services>
        <service id="custom.service" class="Custom\ServiceClass"/>
    </services>
</container>
XML;

            file_put_contents($tempFile, $xmlContent);

            $provider = new ContainerProvider();
            $tool = new ServiceTool($tempDir, $provider);

            $services = $tool->getServices();

            $this->assertArrayHasKey('custom.service', $services);
            $this->assertSame('Custom\ServiceClass', $services['custom.service']);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }
}

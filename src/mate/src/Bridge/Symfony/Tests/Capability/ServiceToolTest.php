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

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Capability\ServiceTool;
use Symfony\AI\Mate\Bridge\Symfony\Exception\ServiceNotFoundException;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

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

        $services = Toon::decode($tool->getServices());

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

        $services = Toon::decode($tool->getServices(), DecodeOptions::lenient());

        $this->assertEmpty($services);
    }

    public function testGetServicesIncludesServicesWithMethodCalls()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices());

        $this->assertArrayHasKey('event_dispatcher', $services);
        $this->assertSame('Symfony\Component\EventDispatcher\EventDispatcher', $services['event_dispatcher']);
    }

    public function testGetServicesIncludesServicesWithTags()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices());

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayHasKey('logger', $services);
    }

    public function testGetServicesResolvesAliases()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices());

        // my_service is an alias to cache.app
        $this->assertArrayHasKey('my_service', $services);
        $this->assertSame('Symfony\Component\Cache\Adapter\FilesystemAdapter', $services['my_service']);
    }

    public function testGetServicesStripsLeadingDotsFromServiceIds()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices());

        // .service_locator.abc123 should be accessible without the leading dot
        $this->assertArrayHasKey('service_locator.abc123', $services);
    }

    public function testGetServicesIncludesServicesWithFactory()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices());

        $this->assertArrayHasKey('router', $services);
        $this->assertSame('Symfony\Component\Routing\Router', $services['router']);
    }

    public function testGetServicesFiltersByQuery()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices('cache'));

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayNotHasKey('logger', $services);
        $this->assertArrayNotHasKey('event_dispatcher', $services);
    }

    public function testGetServicesFiltersByClassName()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices('NullLogger'));

        $this->assertArrayHasKey('logger', $services);
        $this->assertArrayNotHasKey('cache.app', $services);
    }

    public function testGetServicesFilterIsCaseInsensitive()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices('CACHE'));

        $this->assertArrayHasKey('cache.app', $services);
    }

    public function testGetServicesWithEmptyQueryReturnsAll()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $allServices = Toon::decode($tool->getServices());
        $emptyQueryServices = Toon::decode($tool->getServices(''));

        $this->assertSame($allServices, $emptyQueryServices);
    }

    public function testGetServicesWithNonMatchingQueryReturnsEmpty()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices('nonexistent_service_xyz'), DecodeOptions::lenient());

        $this->assertEmpty($services);
    }

    public function testGetServicesFiltersByTag()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices(tag: 'kernel.event_listener'));

        $this->assertArrayHasKey('app.event_listener', $services);
        $this->assertSame('App\EventListener\RequestListener', $services['app.event_listener']);
    }

    public function testGetServicesFiltersByTagReturnsOnlyMatchingServices()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices(tag: 'cache.pool'));

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayNotHasKey('logger', $services);
        $this->assertArrayNotHasKey('event_dispatcher', $services);
        $this->assertArrayNotHasKey('app.event_listener', $services);
    }

    public function testGetServicesFiltersByTagWithNoMatch()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices(tag: 'nonexistent.tag'), DecodeOptions::lenient());

        $this->assertEmpty($services);
    }

    public function testGetServicesFiltersByTagAndQuery()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = Toon::decode($tool->getServices(query: 'listener', tag: 'kernel.event_listener'));

        $this->assertArrayHasKey('app.event_listener', $services);
        $this->assertArrayNotHasKey('cache.app', $services);
        $this->assertArrayNotHasKey('logger', $services);
    }

    public function testGetServiceDetailReturnsServiceInfo()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $detail = Toon::decode($tool->getServiceDetail('cache.app'));

        $this->assertSame('cache.app', $detail['id']);
        $this->assertSame(FilesystemAdapter::class, $detail['class']);
        $this->assertIsArray($detail['tags']);
        $this->assertIsArray($detail['calls']);
    }

    public function testGetServiceDetailIncludesTags()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $detail = Toon::decode($tool->getServiceDetail('logger'));

        $this->assertCount(1, $detail['tags']);
        $this->assertSame('monolog.logger', $detail['tags'][0]['name']);
        $this->assertSame('app', $detail['tags'][0]['channel']);
    }

    public function testGetServiceDetailIncludesCalls()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $detail = Toon::decode($tool->getServiceDetail('event_dispatcher'));

        $this->assertContains('addListener', $detail['calls']);
    }

    public function testGetServiceDetailIncludesFactory()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $detail = Toon::decode($tool->getServiceDetail('router'));

        $this->assertSame('RouterFactory::create', $detail['factory']);
    }

    public function testGetServiceDetailThrowsForUnknownService()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $this->expectException(ServiceNotFoundException::class);
        $tool->getServiceDetail('nonexistent.service.xyz');
    }

    public function testGetServiceDetailThrowsWhenContainerNotFound()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool('/non/existent/directory', $provider);

        $this->expectException(ServiceNotFoundException::class);
        $tool->getServiceDetail('cache.app');
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

            $services = Toon::decode($tool->getServices());

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

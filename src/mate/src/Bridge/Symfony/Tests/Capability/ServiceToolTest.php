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
use Symfony\AI\Mate\Bridge\Symfony\Exception\ContainerNotDumpedException;
use Symfony\AI\Mate\Bridge\Symfony\Exception\ServiceNotFoundException;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
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

        $services = $this->decodeUntrusted($tool->getServices())['services'];

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayHasKey('logger', $services);
        $this->assertArrayHasKey('event_dispatcher', $services);

        $this->assertSame('Symfony\Component\Cache\Adapter\FilesystemAdapter', $services['cache.app']);
        $this->assertSame('Psr\Log\NullLogger', $services['logger']);
        $this->assertSame('Symfony\Component\EventDispatcher\EventDispatcher', $services['event_dispatcher']);
    }

    public function testGetServicesFailsWhenNoContainerHasBeenDumped()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool('/non/existent/directory', $provider);

        $this->expectException(ContainerNotDumpedException::class);
        $this->expectExceptionMessage('No compiled container found under "/non/existent/directory"');

        $tool->getServices();
    }

    public function testGetServicesReportsTheMatchCountAndWhetherItCutTheList()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $full = $this->decodeUntrusted($tool->getServices());
        $this->assertFalse($full['truncated']);
        $this->assertSame(\count($full['services']), $full['count']);

        $capped = $this->decodeUntrusted($tool->getServices(limit: 1));
        $this->assertTrue($capped['truncated']);
        $this->assertCount(1, $capped['services']);
        $this->assertSame($full['count'], $capped['count']);
    }

    public function testGetServicesIncludesServicesWithMethodCalls()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices())['services'];

        $this->assertArrayHasKey('event_dispatcher', $services);
        $this->assertSame('Symfony\Component\EventDispatcher\EventDispatcher', $services['event_dispatcher']);
    }

    public function testGetServicesIncludesServicesWithTags()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices())['services'];

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayHasKey('logger', $services);
    }

    public function testGetServicesResolvesAliases()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices())['services'];

        // my_service is an alias to cache.app
        $this->assertArrayHasKey('my_service', $services);
        $this->assertSame('Symfony\Component\Cache\Adapter\FilesystemAdapter', $services['my_service']);
    }

    public function testGetServicesStripsLeadingDotsFromServiceIds()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices())['services'];

        // .service_locator.abc123 should be accessible without the leading dot
        $this->assertArrayHasKey('service_locator.abc123', $services);
    }

    public function testGetServicesIncludesServicesWithFactory()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices())['services'];

        $this->assertArrayHasKey('router', $services);
        $this->assertSame('Symfony\Component\Routing\Router', $services['router']);
    }

    public function testGetServicesFiltersByQuery()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices('cache'))['services'];

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayNotHasKey('logger', $services);
        $this->assertArrayNotHasKey('event_dispatcher', $services);
    }

    public function testGetServicesFiltersByClassName()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices('NullLogger'))['services'];

        $this->assertArrayHasKey('logger', $services);
        $this->assertArrayNotHasKey('cache.app', $services);
    }

    public function testGetServicesFilterIsCaseInsensitive()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices('CACHE'))['services'];

        $this->assertArrayHasKey('cache.app', $services);
    }

    public function testGetServicesWithEmptyQueryReturnsAll()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $allServices = $this->decodeUntrusted($tool->getServices())['services'];
        $emptyQueryServices = $this->decodeUntrusted($tool->getServices(''))['services'];

        $this->assertSame($allServices, $emptyQueryServices);
    }

    public function testGetServicesWithNonMatchingQueryReturnsEmpty()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices('nonexistent_service_xyz'), DecodeOptions::lenient())['services'];

        $this->assertEmpty($services);
    }

    public function testGetServicesFiltersByTag()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices(tag: 'kernel.event_listener'))['services'];

        $this->assertArrayHasKey('app.event_listener', $services);
        $this->assertSame('App\EventListener\RequestListener', $services['app.event_listener']);
    }

    public function testGetServicesFiltersByTagReturnsOnlyMatchingServices()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices(tag: 'cache.pool'))['services'];

        $this->assertArrayHasKey('cache.app', $services);
        $this->assertArrayNotHasKey('logger', $services);
        $this->assertArrayNotHasKey('event_dispatcher', $services);
        $this->assertArrayNotHasKey('app.event_listener', $services);
    }

    public function testGetServicesFiltersByTagWithNoMatch()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices(tag: 'nonexistent.tag'), DecodeOptions::lenient())['services'];

        $this->assertEmpty($services);
    }

    public function testGetServicesFiltersByTagAndQuery()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $services = $this->decodeUntrusted($tool->getServices(query: 'listener', tag: 'kernel.event_listener'))['services'];

        $this->assertArrayHasKey('app.event_listener', $services);
        $this->assertArrayNotHasKey('cache.app', $services);
        $this->assertArrayNotHasKey('logger', $services);
    }

    public function testGetServiceDetailReturnsServiceInfo()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $detail = $this->decodeUntrusted($tool->getServiceDetail('cache.app'));

        $this->assertSame('cache.app', $detail['id']);
        $this->assertSame(FilesystemAdapter::class, $detail['class']);
        $this->assertIsArray($detail['tags']);
        $this->assertIsArray($detail['calls']);
    }

    public function testGetServiceDetailIncludesTags()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $detail = $this->decodeUntrusted($tool->getServiceDetail('logger'));

        $this->assertCount(1, $detail['tags']);
        $this->assertSame('monolog.logger', $detail['tags'][0]['name']);
        $this->assertSame('app', $detail['tags'][0]['channel']);
    }

    public function testGetServiceDetailIncludesCalls()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $detail = $this->decodeUntrusted($tool->getServiceDetail('event_dispatcher'));

        $this->assertContains('addListener', $detail['calls']);
    }

    public function testGetServiceDetailIncludesFactory()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $detail = $this->decodeUntrusted($tool->getServiceDetail('router'));

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

        $this->expectException(ContainerNotDumpedException::class);
        $tool->getServiceDetail('cache.app');
    }

    public function testSupportsMultipleCacheDirectories()
    {
        $tool = $this->createMultiKernelTool();

        $byContext = $this->decodeUntrusted($tool->getServices());

        $this->assertArrayHasKey('website', $byContext);
        $this->assertArrayHasKey('admin', $byContext);
        $this->assertArrayHasKey('event_dispatcher', $byContext['website']['services']);
        $this->assertArrayHasKey('admin.dashboard', $byContext['admin']['services']);
        $this->assertArrayNotHasKey('event_dispatcher', $byContext['admin']['services']);
        $this->assertSame('Admin\Controller\DashboardController', $byContext['admin']['services']['admin.dashboard']);
    }

    public function testGetServicesFiltersByContext()
    {
        $tool = $this->createMultiKernelTool();

        $byContext = $this->decodeUntrusted($tool->getServices(context: 'admin'));

        $this->assertSame(['admin'], array_keys($byContext));
        $this->assertArrayHasKey('admin.dashboard', $byContext['admin']['services']);
    }

    public function testGetServicesAppliesFiltersPerContext()
    {
        $tool = $this->createMultiKernelTool();

        $byContext = $this->decodeUntrusted($tool->getServices(tag: 'cache.pool'));

        $this->assertArrayNotHasKey('logger', $byContext['website']['services']);
        $this->assertSame(['cache.app'], array_keys($byContext['admin']['services']));
        $this->assertSame(FilesystemAdapter::class, $byContext['website']['services']['cache.app']);
        $this->assertSame(ArrayAdapter::class, $byContext['admin']['services']['cache.app']);
    }

    public function testGetServiceDetailReturnsTheContextItWasFoundIn()
    {
        $tool = $this->createMultiKernelTool();

        $detail = $this->decodeUntrusted($tool->getServiceDetail('admin.dashboard'));

        $this->assertSame('admin.dashboard', $detail['id']);
        $this->assertSame('admin', $detail['context']);
    }

    public function testGetServiceDetailPrefersTheFirstMatchingContext()
    {
        $tool = $this->createMultiKernelTool();

        $detail = $this->decodeUntrusted($tool->getServiceDetail('cache.app'));

        $this->assertSame(FilesystemAdapter::class, $detail['class']);
        $this->assertSame('website', $detail['context']);
    }

    public function testGetServiceDetailFiltersByContext()
    {
        $tool = $this->createMultiKernelTool();

        $detail = $this->decodeUntrusted($tool->getServiceDetail('cache.app', 'admin'));

        $this->assertSame(ArrayAdapter::class, $detail['class']);
        $this->assertSame('admin', $detail['context']);
    }

    public function testGetServiceDetailThrowsForServiceMissingInTheGivenContext()
    {
        $tool = $this->createMultiKernelTool();

        $this->expectException(ServiceNotFoundException::class);
        $tool->getServiceDetail('event_dispatcher', 'admin');
    }

    public function testGetServiceDetailOmitsContextForSingleCacheDirectory()
    {
        $provider = new ContainerProvider();
        $tool = new ServiceTool($this->fixturesDir, $provider);

        $detail = $this->decodeUntrusted($tool->getServiceDetail('cache.app'));

        $this->assertArrayNotHasKey('context', $detail);
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

            $services = $this->decodeUntrusted($tool->getServices())['services'];

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

    private function createMultiKernelTool(): ServiceTool
    {
        return new ServiceTool([
            'website' => $this->fixturesDir,
            'admin' => $this->fixturesDir.'/admin',
        ], new ContainerProvider());
    }

    /**
     * Decodes a tool response that is expected to carry the untrusted-data
     * envelope, asserts the security notice is present, and returns the payload.
     *
     * @return array<string, mixed>
     */
    private function decodeUntrusted(string $response, ?DecodeOptions $options = null): array
    {
        $decoded = null !== $options ? Toon::decode($response, $options) : Toon::decode($response);

        $this->assertIsArray($decoded);
        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $decoded['_security_notice']);

        return $decoded['untrusted_data'];
    }
}

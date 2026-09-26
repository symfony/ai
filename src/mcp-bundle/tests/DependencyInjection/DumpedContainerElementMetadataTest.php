<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\DependencyInjection;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Capability\RegistryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Dumps the container like a kernel does, as the dumper turns "%...%" spans into runtime parameter lookups.
 */
final class DumpedContainerElementMetadataTest extends TestCase
{
    private string $dumpFile;

    protected function setUp(): void
    {
        $this->dumpFile = tempnam(sys_get_temp_dir(), 'mcp_bundle_container_');
    }

    protected function tearDown(): void
    {
        if (is_file($this->dumpFile)) {
            unlink($this->dumpFile);
        }
    }

    public function testPercentSignsInElementMetadataReachTheRegistryLiterally()
    {
        $container = $this->dumpedContainer();

        // Building the server registers the elements with the registry.
        $container->get('test.server');

        $registry = $container->get('test.registry');
        $this->assertInstanceOf(RegistryInterface::class, $registry);

        $tool = $registry->getTool('search')->tool;
        $this->assertSame('Get 50%off% today', $tool->title);
        $this->assertSame('Filter with "%" wildcards, 100% free', $tool->description);
        $this->assertSame('Filter, "%" wildcard. Default "%" matches everything.', $tool->inputSchema['properties']['query']['description']);
        $this->assertSame('%', $tool->inputSchema['properties']['query']['default']);
        $this->assertSame(['%key%' => '%value%'], $tool->meta);

        $prompt = $registry->getPrompt('discount')->prompt;
        $this->assertSame('50%off%', $prompt->title);
        $this->assertSame('Get 50% off', $prompt->description);

        $resource = $registry->getResource('file:///my%20docs/readme', false)->resource;
        $this->assertSame('%title%', $resource->title);
        $this->assertSame('100% up to date', $resource->description);

        $template = $registry->getResourceTemplate('file:///my%20docs/{name}')->resourceTemplate;
        $this->assertSame('100% of the docs', $template->description);
    }

    private function dumpedContainer(): ContainerInterface
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.project_dir', __DIR__);

        $bundle = new McpBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('mcp', [
            'servers' => [
                'default' => [
                    'transports' => ['http' => false],
                    'session' => ['store' => 'memory'],
                    'registry' => '*',
                ],
            ],
        ]);
        $bundle->build($container);

        $container->register('logger', NullLogger::class);
        $container->register('event_dispatcher', EventDispatcher::class);
        foreach ([PercentSearchTool::class, PercentDiscountPrompt::class, PercentDocsResource::class] as $class) {
            $container->setDefinition($class, (new Definition($class))->setAutoconfigured(true));
        }

        $container->setAlias('test.server', 'mcp.server.default')->setPublic(true);
        $container->setAlias('test.registry', 'mcp.server.default.registry')->setPublic(true);

        $container->compile();

        $class = 'McpBundleElementMetadataContainer'.bin2hex(random_bytes(4));
        file_put_contents($this->dumpFile, (new PhpDumper($container))->dump(['class' => $class]));
        require $this->dumpFile;

        return new $class();
    }
}

class PercentSearchTool
{
    #[McpTool(
        name: 'search',
        title: 'Get 50%off% today',
        description: 'Filter with "%" wildcards, 100% free',
        meta: ['%key%' => '%value%'],
    )]
    public function __invoke(
        #[Schema(description: 'Filter, "%" wildcard. Default "%" matches everything.')]
        string $query = '%',
    ): string {
        return $query;
    }
}

class PercentDiscountPrompt
{
    #[McpPrompt(name: 'discount', title: '50%off%', description: 'Get 50% off')]
    public function __invoke(): string
    {
        return 'Get 50% off';
    }
}

class PercentDocsResource
{
    #[McpResource(uri: 'file:///my%20docs/readme', name: 'readme', title: '%title%', description: '100% up to date')]
    public function readme(): string
    {
        return '100%';
    }

    #[McpResourceTemplate(uriTemplate: 'file:///my%20docs/{name}', name: 'docs', description: '100% of the docs')]
    public function docs(string $name): string
    {
        return $name;
    }
}

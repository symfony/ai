<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery;

use Mcp\Capability\Discovery\DiscovererInterface;
use Mcp\Capability\Discovery\DiscoveryState;
use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\Registry\ResourceReference;
use Mcp\Capability\Registry\ResourceTemplateReference;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class FilteredDiscoveryLoaderTest extends TestCase
{
    public function testLoadWithEnabledFeatures()
    {
        $tool1 = $this->createToolReference('tool1');
        $tool2 = $this->createToolReference('tool2');

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool1, 'tool2' => $tool2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->with('/base/path', ['src'])
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registeredTools = $this->captureRegisteredTools($registry);

        $this->createLoader($discoverer, [
            'vendor/package-a' => ['dirs' => ['src'], 'includes' => []],
        ], [])->load($registry);

        $this->assertSame(['tool1', 'tool2'], $registeredTools->names);
    }

    public function testLoadWithDisabledFeatures()
    {
        $tool1 = $this->createToolReference('tool1');
        $tool2 = $this->createToolReference('tool2');

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool1, 'tool2' => $tool2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registeredTools = $this->captureRegisteredTools($registry);

        $this->createLoader($discoverer, [
            'vendor/package-a' => ['dirs' => ['src'], 'includes' => []],
        ], [
            'vendor/package-a' => ['tool2' => ['enabled' => false]],
        ])->load($registry);

        $this->assertSame(['tool1'], $registeredTools->names);
    }

    public function testLoadWithMixedEnabledDisabledFeatures()
    {
        $tool1 = $this->createToolReference('tool1');
        $tool2 = $this->createToolReference('tool2');
        $tool3 = $this->createToolReference('tool3');

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool1, 'tool2' => $tool2, 'tool3' => $tool3],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registeredTools = $this->captureRegisteredTools($registry);

        $this->createLoader($discoverer, [
            'vendor/package-a' => ['dirs' => ['src'], 'includes' => []],
        ], [
            'vendor/package-a' => [
                'tool1' => ['enabled' => true],
                'tool2' => ['enabled' => false],
                'tool3' => ['enabled' => true],
            ],
        ])->load($registry);

        $this->assertSame(['tool1', 'tool3'], $registeredTools->names);
    }

    public function testLoadFiltersResources()
    {
        $resource1 = new ResourceReference(new ResourceDefinition('config://resource1', 'resource1', description: 'Resource 1'), 'method1');
        $resource2 = new ResourceReference(new ResourceDefinition('config://resource2', 'resource2', description: 'Resource 2'), 'method2');

        $discoveryState = new DiscoveryState(
            resources: ['config://resource1' => $resource1, 'config://resource2' => $resource2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registered = [];
        $registry->method('registerResource')->willReturnCallback(
            static function (ResourceDefinition $resource, $handler) use (&$registered) {
                $registered[] = $resource->uri;

                return new ResourceReference($resource, $handler);
            }
        );

        $this->createLoader($discoverer, [
            'vendor/package-a' => ['dirs' => ['src'], 'includes' => []],
        ], [
            'vendor/package-a' => ['config://resource2' => ['enabled' => false]],
        ])->load($registry);

        $this->assertSame(['config://resource1'], $registered);
    }

    public function testLoadFiltersPrompts()
    {
        $prompt1 = new PromptReference(new Prompt('prompt1', 'Prompt 1'), 'method1');
        $prompt2 = new PromptReference(new Prompt('prompt2', 'Prompt 2'), 'method2');

        $discoveryState = new DiscoveryState(
            prompts: ['prompt1' => $prompt1, 'prompt2' => $prompt2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registered = [];
        $registry->method('registerPrompt')->willReturnCallback(
            static function (Prompt $prompt, $handler, array $completionProviders = []) use (&$registered) {
                $registered[] = $prompt->name;

                return new PromptReference($prompt, $handler, $completionProviders);
            }
        );

        $this->createLoader($discoverer, [
            'vendor/package-a' => ['dirs' => ['src'], 'includes' => []],
        ], [
            'vendor/package-a' => ['prompt2' => ['enabled' => false]],
        ])->load($registry);

        $this->assertSame(['prompt1'], $registered);
    }

    public function testLoadFiltersResourceTemplates()
    {
        $template1 = new ResourceTemplateReference(new ResourceTemplate('config://{key}', 'template1', 'Template 1'), 'method1');
        $template2 = new ResourceTemplateReference(new ResourceTemplate('config://{id}', 'template2', 'Template 2'), 'method2');

        $discoveryState = new DiscoveryState(
            resourceTemplates: ['template1' => $template1, 'template2' => $template2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registered = [];
        $registry->method('registerResourceTemplate')->willReturnCallback(
            static function (ResourceTemplate $template, $handler, array $completionProviders = []) use (&$registered) {
                $registered[] = $template->name;

                return new ResourceTemplateReference($template, $handler, $completionProviders);
            }
        );

        $this->createLoader($discoverer, [
            'vendor/package-a' => ['dirs' => ['src'], 'includes' => []],
        ], [
            'vendor/package-a' => ['template2' => ['enabled' => false]],
        ])->load($registry);

        $this->assertSame(['template1'], $registered);
    }

    public function testLoadWithEmptyFiltersConfiguration()
    {
        $tool = $this->createToolReference('tool1');

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registeredTools = $this->captureRegisteredTools($registry);

        $this->createLoader($discoverer, [
            'vendor/package-a' => ['dirs' => ['src'], 'includes' => []],
        ], [])->load($registry);

        $this->assertSame(['tool1'], $registeredTools->names);
    }

    public function testLoadWithMultiplePackages()
    {
        $tool1 = $this->createToolReference('tool1');
        $tool2 = $this->createToolReference('tool2');

        $discoveryStateA = new DiscoveryState(tools: ['tool1' => $tool1]);
        $discoveryStateB = new DiscoveryState(tools: ['tool2' => $tool2]);

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->exactly(2))
            ->method('discover')
            ->willReturnOnConsecutiveCalls($discoveryStateA, $discoveryStateB);

        $registry = $this->createMock(RegistryInterface::class);
        $registeredTools = $this->captureRegisteredTools($registry);

        $this->createLoader($discoverer, [
            'vendor/package-a' => ['dirs' => ['src'], 'includes' => []],
            'vendor/package-b' => ['dirs' => ['src'], 'includes' => []],
        ], [])->load($registry);

        $this->assertSame(['tool1', 'tool2'], $registeredTools->names);
    }

    public function testLoadFiltersNonExistentFeatures()
    {
        $tool = $this->createToolReference('tool1');

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registeredTools = $this->captureRegisteredTools($registry);

        $this->createLoader($discoverer, [
            'vendor/package-a' => ['dirs' => ['src'], 'includes' => []],
        ], [
            'vendor/package-a' => ['nonexistent_tool' => ['enabled' => false]],
        ])->load($registry);

        $this->assertSame(['tool1'], $registeredTools->names);
    }

    private function createToolReference(string $name): ToolReference
    {
        return new ToolReference(
            new Tool(name: $name, title: null, inputSchema: ['type' => 'object', 'properties' => [], 'required' => []], description: 'description', annotations: null),
            'method_'.$name,
        );
    }

    /**
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     * @param array<string, array<string, array{enabled: bool}>>       $disabledFeatures
     */
    private function createLoader(DiscovererInterface $discoverer, array $extensions, array $disabledFeatures): FilteredDiscoveryLoader
    {
        return new FilteredDiscoveryLoader(
            '/base/path',
            $extensions,
            $disabledFeatures,
            $discoverer,
            new NullLogger(),
        );
    }

    /**
     * Records the names of the tools registered on the given registry mock.
     */
    private function captureRegisteredTools(RegistryInterface&\PHPUnit\Framework\MockObject\MockObject $registry): object
    {
        $capture = new class {
            /** @var string[] */
            public array $names = [];
        };

        $registry->method('registerTool')->willReturnCallback(
            static function (Tool $tool, $handler) use ($capture) {
                $capture->names[] = $tool->name;

                return new ToolReference($tool, $handler);
            }
        );

        return $capture;
    }
}

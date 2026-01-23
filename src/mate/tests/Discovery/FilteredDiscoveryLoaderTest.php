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
use Mcp\Schema\Resource;
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
        $tool1 = new ToolReference(new Tool('tool1', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method1', false);
        $tool2 = new ToolReference(new Tool('tool2', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method2', false);

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool1, 'tool2' => $tool2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->with('/base/path', ['src'])
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('setDiscoveryState')
            ->with($this->callback(static function (DiscoveryState $state) {
                return 2 === \count($state->getTools());
            }));

        $bridges = [
            'vendor/package-a' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
        ];

        $disabledFeatures = [];

        $loader = new FilteredDiscoveryLoader(
            '/base/path',
            $bridges,
            $disabledFeatures,
            $discoverer,
            new NullLogger()
        );

        $loader->load($registry);
    }

    public function testLoadWithDisabledFeatures()
    {
        $tool1 = new ToolReference(new Tool('tool1', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method1', false);
        $tool2 = new ToolReference(new Tool('tool2', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method2', false);

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool1, 'tool2' => $tool2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('setDiscoveryState')
            ->with($this->callback(static function (DiscoveryState $state) {
                $tools = $state->getTools();

                // Only tool1 should be present (tool2 is disabled)
                return 1 === \count($tools) && isset($tools['tool1']);
            }));

        $bridges = [
            'vendor/package-a' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
        ];

        $disabledFeatures = [
            'vendor/package-a' => [
                'tool2' => ['enabled' => false],
            ],
        ];

        $loader = new FilteredDiscoveryLoader(
            '/base/path',
            $bridges,
            $disabledFeatures,
            $discoverer,
            new NullLogger()
        );

        $loader->load($registry);
    }

    public function testLoadWithMixedEnabledDisabledFeatures()
    {
        $tool1 = new ToolReference(new Tool('tool1', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method1', false);
        $tool2 = new ToolReference(new Tool('tool2', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method2', false);
        $tool3 = new ToolReference(new Tool('tool3', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method3', false);

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool1, 'tool2' => $tool2, 'tool3' => $tool3],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('setDiscoveryState')
            ->with($this->callback(static function (DiscoveryState $state) {
                $tools = $state->getTools();

                // tool1 and tool3 should be present (tool2 is disabled)
                return 2 === \count($tools) && isset($tools['tool1']) && isset($tools['tool3']) && !isset($tools['tool2']);
            }));

        $bridges = [
            'vendor/package-a' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
        ];

        $disabledFeatures = [
            'vendor/package-a' => [
                'tool1' => ['enabled' => true],
                'tool2' => ['enabled' => false],
                'tool3' => ['enabled' => true],
            ],
        ];

        $loader = new FilteredDiscoveryLoader(
            '/base/path',
            $bridges,
            $disabledFeatures,
            $discoverer,
            new NullLogger()
        );

        $loader->load($registry);
    }

    public function testLoadFiltersResources()
    {
        $resource1 = new ResourceReference(new Resource('config://resource1', 'resource1', 'Resource 1'), 'method1', false);
        $resource2 = new ResourceReference(new Resource('config://resource2', 'resource2', 'Resource 2'), 'method2', false);

        $discoveryState = new DiscoveryState(
            resources: ['config://resource1' => $resource1, 'config://resource2' => $resource2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('setDiscoveryState')
            ->with($this->callback(static function (DiscoveryState $state) {
                $resources = $state->getResources();

                return 1 === \count($resources) && isset($resources['config://resource1']);
            }));

        $bridges = [
            'vendor/package-a' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
        ];

        $disabledFeatures = [
            'vendor/package-a' => [
                'config://resource2' => ['enabled' => false],
            ],
        ];

        $loader = new FilteredDiscoveryLoader(
            '/base/path',
            $bridges,
            $disabledFeatures,
            $discoverer,
            new NullLogger()
        );

        $loader->load($registry);
    }

    public function testLoadFiltersPrompts()
    {
        $prompt1 = new PromptReference(new Prompt('prompt1', 'Prompt 1'), 'method1', false);
        $prompt2 = new PromptReference(new Prompt('prompt2', 'Prompt 2'), 'method2', false);

        $discoveryState = new DiscoveryState(
            prompts: ['prompt1' => $prompt1, 'prompt2' => $prompt2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('setDiscoveryState')
            ->with($this->callback(static function (DiscoveryState $state) {
                $prompts = $state->getPrompts();

                return 1 === \count($prompts) && isset($prompts['prompt1']);
            }));

        $bridges = [
            'vendor/package-a' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
        ];

        $disabledFeatures = [
            'vendor/package-a' => [
                'prompt2' => ['enabled' => false],
            ],
        ];

        $loader = new FilteredDiscoveryLoader(
            '/base/path',
            $bridges,
            $disabledFeatures,
            $discoverer,
            new NullLogger()
        );

        $loader->load($registry);
    }

    public function testLoadFiltersResourceTemplates()
    {
        $template1 = new ResourceTemplateReference(new ResourceTemplate('config://{key}', 'template1', 'Template 1'), 'method1', false);
        $template2 = new ResourceTemplateReference(new ResourceTemplate('config://{id}', 'template2', 'Template 2'), 'method2', false);

        $discoveryState = new DiscoveryState(
            resourceTemplates: ['template1' => $template1, 'template2' => $template2],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('setDiscoveryState')
            ->with($this->callback(static function (DiscoveryState $state) {
                $templates = $state->getResourceTemplates();

                return 1 === \count($templates) && isset($templates['template1']);
            }));

        $bridges = [
            'vendor/package-a' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
        ];

        $disabledFeatures = [
            'vendor/package-a' => [
                'template2' => ['enabled' => false],
            ],
        ];

        $loader = new FilteredDiscoveryLoader(
            '/base/path',
            $bridges,
            $disabledFeatures,
            $discoverer,
            new NullLogger()
        );

        $loader->load($registry);
    }

    public function testLoadWithEmptyFiltersConfiguration()
    {
        $tool = new ToolReference(new Tool('tool1', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method1', false);

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('setDiscoveryState')
            ->with($this->callback(static function (DiscoveryState $state) {
                return 1 === \count($state->getTools());
            }));

        $bridges = [
            'vendor/package-a' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
        ];

        $disabledFeatures = [];

        $loader = new FilteredDiscoveryLoader(
            '/base/path',
            $bridges,
            $disabledFeatures,
            $discoverer,
            new NullLogger()
        );

        $loader->load($registry);
    }

    public function testLoadWithMultiplePackages()
    {
        $tool1 = new ToolReference(new Tool('tool1', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method1', false);
        $tool2 = new ToolReference(new Tool('tool2', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method2', false);

        $discoveryStateA = new DiscoveryState(tools: ['tool1' => $tool1]);
        $discoveryStateB = new DiscoveryState(tools: ['tool2' => $tool2]);

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->exactly(2))
            ->method('discover')
            ->willReturnOnConsecutiveCalls($discoveryStateA, $discoveryStateB);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('setDiscoveryState')
            ->with($this->callback(static function (DiscoveryState $state) {
                return 2 === \count($state->getTools());
            }));

        $bridges = [
            'vendor/package-a' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
            'vendor/package-b' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
        ];

        $disabledFeatures = [];

        $loader = new FilteredDiscoveryLoader(
            '/base/path',
            $bridges,
            $disabledFeatures,
            $discoverer,
            new NullLogger()
        );

        $loader->load($registry);
    }

    public function testLoadFiltersNonExistentFeatures()
    {
        $tool = new ToolReference(new Tool('tool1', ['type' => 'object', 'properties' => [], 'required' => []], 'description', null), 'method1', false);

        $discoveryState = new DiscoveryState(
            tools: ['tool1' => $tool],
        );

        $discoverer = $this->createMock(DiscovererInterface::class);
        $discoverer->expects($this->once())
            ->method('discover')
            ->willReturn($discoveryState);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('setDiscoveryState')
            ->with($this->callback(static function (DiscoveryState $state) {
                // tool1 should still be present even though nonexistent_tool is configured
                return 1 === \count($state->getTools()) && isset($state->getTools()['tool1']);
            }));

        $bridges = [
            'vendor/package-a' => [
                'dirs' => ['src'],
                'includes' => [],
            ],
        ];

        $disabledFeatures = [
            'vendor/package-a' => [
                'nonexistent_tool' => ['enabled' => false],
            ],
        ];

        $loader = new FilteredDiscoveryLoader(
            '/base/path',
            $bridges,
            $disabledFeatures,
            $discoverer,
            new NullLogger()
        );

        $loader->load($registry);
    }
}

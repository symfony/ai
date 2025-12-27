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

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Discovery\CapabilityCollector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CapabilityCollectorTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/Fixtures';
    }

    public function testCollectCapabilitiesReturnsStructure()
    {
        $collector = new CapabilityCollector(
            $this->fixturesDir.'/with-ai-mate-config',
            [],
            [],
            new NullLogger()
        );

        $extension = [
            'dirs' => ['mate/src'],
            'includes' => [],
        ];

        $capabilities = $collector->collectCapabilities('test/extension', $extension);

        $this->assertArrayHasKey('tools', $capabilities);
        $this->assertArrayHasKey('resources', $capabilities);
        $this->assertArrayHasKey('prompts', $capabilities);
        $this->assertArrayHasKey('resource_templates', $capabilities);
    }

    public function testCollectCapabilitiesWithEmptyDirectories()
    {
        $collector = new CapabilityCollector(
            $this->fixturesDir.'/with-ai-mate-config',
            [],
            [],
            new NullLogger()
        );

        $extension = [
            'dirs' => ['mate/src'],
            'includes' => [],
        ];

        $capabilities = $collector->collectCapabilities('test/extension', $extension);

        // Empty directories should result in empty capability arrays
        $this->assertIsArray($capabilities['tools']);
        $this->assertIsArray($capabilities['resources']);
        $this->assertIsArray($capabilities['prompts']);
        $this->assertIsArray($capabilities['resource_templates']);
    }

    public function testCollectCapabilitiesWithIncludes()
    {
        $collector = new CapabilityCollector(
            $this->fixturesDir.'/with-ai-mate-config',
            [],
            [],
            new NullLogger()
        );

        $extension = [
            'dirs' => [],
            'includes' => ['mate/config.php'],
        ];

        $capabilities = $collector->collectCapabilities('test/extension', $extension);

        $this->assertIsArray($capabilities['tools']);
        $this->assertIsArray($capabilities['resources']);
        $this->assertIsArray($capabilities['prompts']);
        $this->assertIsArray($capabilities['resource_templates']);
    }

    public function testCollectCapabilitiesFormatsTools()
    {
        $collector = new CapabilityCollector(
            $this->fixturesDir.'/with-ai-mate-config',
            [],
            [],
            new NullLogger()
        );

        $extension = [
            'dirs' => ['mate/src'],
            'includes' => [],
        ];

        $capabilities = $collector->collectCapabilities('test/extension', $extension);

        $this->assertIsArray($capabilities['tools']);
        foreach ($capabilities['tools'] as $name => $tool) {
            $this->assertIsString($name);
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('handler', $tool);
            $this->assertArrayHasKey('input_schema', $tool);
        }
    }

    public function testCollectCapabilitiesFormatsResources()
    {
        $collector = new CapabilityCollector(
            $this->fixturesDir.'/with-ai-mate-config',
            [],
            [],
            new NullLogger()
        );

        $extension = [
            'dirs' => ['mate/src'],
            'includes' => [],
        ];

        $capabilities = $collector->collectCapabilities('test/extension', $extension);

        $this->assertIsArray($capabilities['resources']);
        foreach ($capabilities['resources'] as $uri => $resource) {
            $this->assertIsString($uri);
            $this->assertArrayHasKey('uri', $resource);
            $this->assertArrayHasKey('name', $resource);
            $this->assertArrayHasKey('description', $resource);
            $this->assertArrayHasKey('handler', $resource);
            $this->assertArrayHasKey('mime_type', $resource);
        }
    }

    public function testCollectCapabilitiesFormatsPrompts()
    {
        $collector = new CapabilityCollector(
            $this->fixturesDir.'/with-ai-mate-config',
            [],
            [],
            new NullLogger()
        );

        $extension = [
            'dirs' => ['mate/src'],
            'includes' => [],
        ];

        $capabilities = $collector->collectCapabilities('test/extension', $extension);

        $this->assertIsArray($capabilities['prompts']);
        foreach ($capabilities['prompts'] as $name => $prompt) {
            $this->assertIsString($name);
            $this->assertArrayHasKey('name', $prompt);
            $this->assertArrayHasKey('description', $prompt);
            $this->assertArrayHasKey('handler', $prompt);
            $this->assertArrayHasKey('arguments', $prompt);
        }
    }

    public function testCollectCapabilitiesFormatsResourceTemplates()
    {
        $collector = new CapabilityCollector(
            $this->fixturesDir.'/with-ai-mate-config',
            [],
            [],
            new NullLogger()
        );

        $extension = [
            'dirs' => ['mate/src'],
            'includes' => [],
        ];

        $capabilities = $collector->collectCapabilities('test/extension', $extension);

        $this->assertIsArray($capabilities['resource_templates']);
        foreach ($capabilities['resource_templates'] as $uriTemplate => $template) {
            $this->assertIsString($uriTemplate);
            $this->assertArrayHasKey('uri_template', $template);
            $this->assertArrayHasKey('name', $template);
            $this->assertArrayHasKey('description', $template);
            $this->assertArrayHasKey('handler', $template);
        }
    }
}

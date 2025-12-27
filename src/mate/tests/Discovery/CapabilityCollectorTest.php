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

    public function testGetExtensionsToLoad()
    {
        $collector = new CapabilityCollector(
            new NullLogger(),
            $this->fixturesDir.'/with-ai-mate-config'
        );

        $extensions = $collector->getExtensionsToLoad(['vendor/package-a', 'vendor/package-b']);

        $this->assertCount(3, $extensions); // package-a, package-b, and _custom
        $this->assertArrayHasKey('vendor/package-a', $extensions);
        $this->assertArrayHasKey('vendor/package-b', $extensions);
        $this->assertArrayHasKey('_custom', $extensions);
    }

    public function testGetExtensionsToLoadIncludesCustomProject()
    {
        $collector = new CapabilityCollector(
            new NullLogger(),
            $this->fixturesDir.'/with-ai-mate-config'
        );

        $extensions = $collector->getExtensionsToLoad([]);

        // _custom is always included
        $this->assertArrayHasKey('_custom', $extensions);
    }

    public function testCollectCapabilitiesReturnsStructure()
    {
        $collector = new CapabilityCollector(
            new NullLogger(),
            $this->fixturesDir.'/with-ai-mate-config'
        );

        $extensions = [
            'vendor/package-a' => [
                'dirs' => [$this->fixturesDir.'/with-ai-mate-config/vendor/vendor/package-a/src'],
                'includes' => [],
            ],
        ];

        $capabilities = $collector->collectCapabilities($extensions);

        $this->assertArrayHasKey('vendor/package-a', $capabilities);
        $this->assertArrayHasKey('tools', $capabilities['vendor/package-a']);
        $this->assertArrayHasKey('resources', $capabilities['vendor/package-a']);
        $this->assertArrayHasKey('prompts', $capabilities['vendor/package-a']);
        $this->assertArrayHasKey('resource_templates', $capabilities['vendor/package-a']);
    }

    public function testCollectCapabilitiesWithEmptyDirectories()
    {
        $collector = new CapabilityCollector(
            new NullLogger(),
            $this->fixturesDir.'/with-ai-mate-config'
        );

        $extensions = [
            'vendor/package-a' => [
                'dirs' => [$this->fixturesDir.'/with-ai-mate-config/vendor/vendor/package-a/src'],
                'includes' => [],
            ],
        ];

        $capabilities = $collector->collectCapabilities($extensions);

        // Empty directories should result in empty capability arrays
        $this->assertIsArray($capabilities['vendor/package-a']['tools']);
        $this->assertIsArray($capabilities['vendor/package-a']['resources']);
        $this->assertIsArray($capabilities['vendor/package-a']['prompts']);
        $this->assertIsArray($capabilities['vendor/package-a']['resource_templates']);
    }

    public function testCollectCapabilitiesWithMultipleExtensions()
    {
        $collector = new CapabilityCollector(
            new NullLogger(),
            $this->fixturesDir.'/with-ai-mate-config'
        );

        $extensions = [
            'vendor/package-a' => [
                'dirs' => [$this->fixturesDir.'/with-ai-mate-config/vendor/vendor/package-a/src'],
                'includes' => [],
            ],
            'vendor/package-b' => [
                'dirs' => [$this->fixturesDir.'/with-ai-mate-config/vendor/vendor/package-b/src'],
                'includes' => [],
            ],
        ];

        $capabilities = $collector->collectCapabilities($extensions);

        $this->assertCount(2, $capabilities);
        $this->assertArrayHasKey('vendor/package-a', $capabilities);
        $this->assertArrayHasKey('vendor/package-b', $capabilities);
    }
}

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
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ComposerExtensionDiscoveryTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/Fixtures';
    }

    public function testDiscoverPackagesWithAiMateConfig()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-ai-mate-config',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(2, $extensions);
        $this->assertArrayHasKey('vendor/package-a', $extensions);
        $this->assertArrayHasKey('vendor/package-b', $extensions);

        // Check package-a structure
        $this->assertArrayHasKey('dirs', $extensions['vendor/package-a']);
        $this->assertArrayHasKey('includes', $extensions['vendor/package-a']);

        $this->assertContains('vendor/vendor/package-a/src', $extensions['vendor/package-a']['dirs']);
    }

    public function testIgnoresPackagesWithoutAiMateConfig()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/without-ai-mate-config',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(0, $extensions);
    }

    public function testIgnoresPackagesWithoutExtraSection()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/no-extra-section',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(0, $extensions);
    }

    public function testWhitelistFiltering()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-ai-mate-config',
            new NullLogger()
        );

        $enabledExtensions = [
            'vendor/package-a',
        ];

        $extensions = $discovery->discover($enabledExtensions);

        $this->assertCount(1, $extensions);
        $this->assertArrayHasKey('vendor/package-a', $extensions);
        $this->assertArrayNotHasKey('vendor/package-b', $extensions);
    }

    public function testWhitelistWithMultiplePackages()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-ai-mate-config',
            new NullLogger()
        );

        $enabledExtensions = [
            'vendor/package-a',
            'vendor/package-b',
        ];

        $extensions = $discovery->discover($enabledExtensions);

        $this->assertCount(2, $extensions);
        $this->assertArrayHasKey('vendor/package-a', $extensions);
        $this->assertArrayHasKey('vendor/package-b', $extensions);
    }

    public function testExtractsIncludeFiles()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/with-includes',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(1, $extensions);
        $this->assertArrayHasKey('vendor/package-with-includes', $extensions);

        $includes = $extensions['vendor/package-with-includes']['includes'];
        $this->assertNotEmpty($includes);
        $this->assertStringContainsString('config/config.php', $includes[0]);
    }

    public function testHandlesMissingInstalledJson()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/no-installed-json',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(0, $extensions);
    }

    public function testHandlesPackagesWithoutType()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/mixed-types',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        // Should discover packages with ai-mate config regardless of type field
        $this->assertGreaterThanOrEqual(1, $extensions);
    }

    public function testDiscoverRootProjectReturnsEmptyWhenComposerJsonDoesNotExist()
    {
        $discovery = new ComposerExtensionDiscovery(
            $this->fixturesDir.'/no-composer-json',
            new NullLogger()
        );

        $result = $discovery->discoverRootProject();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('dirs', $result);
        $this->assertArrayHasKey('includes', $result);
        $this->assertSame([], $result['dirs']);
        $this->assertSame([], $result['includes']);
    }

    public function testDiscoverRootProjectWithAiMateConfig()
    {
        // Create a temporary directory with a composer.json that has ai-mate config
        $tempDir = sys_get_temp_dir().'/mate-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $composerJson = [
            'name' => 'test/project',
            'extra' => [
                'ai-mate' => [
                    'scan-dirs' => ['src', 'lib'],
                    'includes' => ['config/mate.php'],
                ],
            ],
        ];

        file_put_contents($tempDir.'/composer.json', json_encode($composerJson));

        try {
            $discovery = new ComposerExtensionDiscovery($tempDir, new NullLogger());
            $result = $discovery->discoverRootProject();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('dirs', $result);
            $this->assertArrayHasKey('includes', $result);
            $this->assertSame(['src', 'lib'], $result['dirs']);
            $this->assertSame(['config/mate.php'], $result['includes']);
        } finally {
            unlink($tempDir.'/composer.json');
            rmdir($tempDir);
        }
    }

    public function testDiscoverRootProjectWithoutAiMateConfig()
    {
        // Create a temporary directory with a composer.json without ai-mate config
        $tempDir = sys_get_temp_dir().'/mate-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $composerJson = [
            'name' => 'test/project',
        ];

        file_put_contents($tempDir.'/composer.json', json_encode($composerJson));

        try {
            $discovery = new ComposerExtensionDiscovery($tempDir, new NullLogger());
            $result = $discovery->discoverRootProject();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('dirs', $result);
            $this->assertArrayHasKey('includes', $result);
            $this->assertSame([], $result['dirs']);
            $this->assertSame([], $result['includes']);
        } finally {
            unlink($tempDir.'/composer.json');
            rmdir($tempDir);
        }
    }
}

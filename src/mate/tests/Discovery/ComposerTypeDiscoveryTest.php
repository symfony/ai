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
use Symfony\AI\Mate\Discovery\ComposerTypeDiscovery;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ComposerTypeDiscoveryTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/Fixtures';
    }

    public function testDiscoverPackagesWithAiMateConfig()
    {
        $discovery = new ComposerTypeDiscovery(
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
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/without-ai-mate-config',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(0, $extensions);
    }

    public function testIgnoresPackagesWithoutExtraSection()
    {
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/no-extra-section',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(0, $extensions);
    }

    public function testWhitelistFiltering()
    {
        $discovery = new ComposerTypeDiscovery(
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
        $discovery = new ComposerTypeDiscovery(
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
        $discovery = new ComposerTypeDiscovery(
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
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/no-installed-json',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        $this->assertCount(0, $extensions);
    }

    public function testHandlesPackagesWithoutType()
    {
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/mixed-types',
            new NullLogger()
        );

        $extensions = $discovery->discover();

        // Should discover packages with ai-mate config regardless of type field
        $this->assertGreaterThanOrEqual(1, $extensions);
    }
}

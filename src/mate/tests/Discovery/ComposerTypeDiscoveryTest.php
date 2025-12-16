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

        $bridges = $discovery->discover();

        $this->assertCount(2, $bridges);
        $this->assertArrayHasKey('vendor/package-a', $bridges);
        $this->assertArrayHasKey('vendor/package-b', $bridges);

        // Check package-a structure
        $this->assertArrayHasKey('dirs', $bridges['vendor/package-a']);
        $this->assertArrayHasKey('includes', $bridges['vendor/package-a']);

        $this->assertContains('vendor/vendor/package-a/src', $bridges['vendor/package-a']['dirs']);
    }

    public function testIgnoresPackagesWithoutAiMateConfig()
    {
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/without-ai-mate-config',
            new NullLogger()
        );

        $bridges = $discovery->discover();

        $this->assertCount(0, $bridges);
    }

    public function testIgnoresPackagesWithoutExtraSection()
    {
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/no-extra-section',
            new NullLogger()
        );

        $bridges = $discovery->discover();

        $this->assertCount(0, $bridges);
    }

    public function testWhitelistFiltering()
    {
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/with-ai-mate-config',
            new NullLogger()
        );

        $enabledBridges = [
            'vendor/package-a',
        ];

        $bridges = $discovery->discover($enabledBridges);

        $this->assertCount(1, $bridges);
        $this->assertArrayHasKey('vendor/package-a', $bridges);
        $this->assertArrayNotHasKey('vendor/package-b', $bridges);
    }

    public function testWhitelistWithMultiplePackages()
    {
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/with-ai-mate-config',
            new NullLogger()
        );

        $enabledBridges = [
            'vendor/package-a',
            'vendor/package-b',
        ];

        $bridges = $discovery->discover($enabledBridges);

        $this->assertCount(2, $bridges);
        $this->assertArrayHasKey('vendor/package-a', $bridges);
        $this->assertArrayHasKey('vendor/package-b', $bridges);
    }

    public function testExtractsIncludeFiles()
    {
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/with-includes',
            new NullLogger()
        );

        $bridges = $discovery->discover();

        $this->assertCount(1, $bridges);
        $this->assertArrayHasKey('vendor/package-with-includes', $bridges);

        $includes = $bridges['vendor/package-with-includes']['includes'];
        $this->assertNotEmpty($includes);
        $this->assertStringContainsString('config/services.php', $includes[0]);
    }

    public function testHandlesMissingInstalledJson()
    {
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/no-installed-json',
            new NullLogger()
        );

        $bridges = $discovery->discover();

        $this->assertCount(0, $bridges);
    }

    public function testHandlesPackagesWithoutType()
    {
        $discovery = new ComposerTypeDiscovery(
            $this->fixturesDir.'/mixed-types',
            new NullLogger()
        );

        $bridges = $discovery->discover();

        // Should discover packages with ai-mate config regardless of type field
        $this->assertGreaterThanOrEqual(1, $bridges);
    }
}

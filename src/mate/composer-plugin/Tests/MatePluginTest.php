<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\ComposerPlugin\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\IO\BufferIO;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\ComposerPlugin\MatePlugin;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MatePluginTest extends TestCase
{
    public function testSubscribedEvents()
    {
        $events = MatePlugin::getSubscribedEvents();

        $this->assertArrayHasKey(ScriptEvents::POST_INSTALL_CMD, $events);
        $this->assertArrayHasKey(ScriptEvents::POST_UPDATE_CMD, $events);
        $this->assertSame('onPostInstallOrUpdate', $events[ScriptEvents::POST_INSTALL_CMD]);
        $this->assertSame('onPostInstallOrUpdate', $events[ScriptEvents::POST_UPDATE_CMD]);
    }

    public function testSuggestsInitWhenExtensionsFileDoesNotExist()
    {
        $io = new BufferIO();
        $rootDir = sys_get_temp_dir().'/mate-plugin-test-'.uniqid();
        mkdir($rootDir, 0755, true);

        $plugin = new MatePlugin();
        $plugin->activate($this->createComposerMock($rootDir), $io);

        try {
            $plugin->onPostInstallOrUpdate($this->createEventMock($rootDir));

            $output = $io->getOutput();
            $this->assertStringContainsString('vendor/bin/mate init', $output);
        } finally {
            $this->removeDirectory($rootDir);
        }
    }

    public function testSkipsWhenMateBinaryDoesNotExist()
    {
        $io = new BufferIO();
        $rootDir = sys_get_temp_dir().'/mate-plugin-test-'.uniqid();
        mkdir($rootDir.'/mate', 0755, true);
        file_put_contents($rootDir.'/mate/extensions.php', "<?php\nreturn [];\n");

        $plugin = new MatePlugin();
        $plugin->activate($this->createComposerMock($rootDir), $io);

        try {
            $plugin->onPostInstallOrUpdate($this->createEventMock($rootDir));

            $output = $io->getOutput();
            $this->assertStringNotContainsString('Discovering extensions', $output);
        } finally {
            $this->removeDirectory($rootDir);
        }
    }

    /**
     * Regression test for the bug where the plugin trusted getcwd() to find the
     * project root. Composer resolves the "vendor-dir" config to an absolute path
     * independently of the process' current working directory (e.g. a monorepo,
     * CI running from a subfolder, or `composer --working-dir=...`), so the plugin
     * must derive the root from Composer's config instead of getcwd().
     */
    public function testUsesComposerVendorDirInsteadOfCurrentWorkingDirectory()
    {
        $io = new BufferIO();
        $rootDir = sys_get_temp_dir().'/mate-plugin-test-'.uniqid();
        mkdir($rootDir.'/mate', 0755, true);
        file_put_contents($rootDir.'/mate/extensions.php', "<?php\nreturn [];\n");
        mkdir($rootDir.'/vendor/bin', 0755, true);
        file_put_contents($rootDir.'/vendor/bin/mate', "<?php\necho 'MATE-DISCOVER-RAN';\n");

        // Simulates the real-world shape from the bug report: Composer is invoked
        // while the shell's cwd is nested somewhere below the actual project root,
        // e.g. a Symfony project fixture nested inside an outer monorepo directory.
        $unrelatedCwd = $rootDir.'/nested/unrelated/cwd';
        mkdir($unrelatedCwd, 0755, true);

        $plugin = new MatePlugin();
        $plugin->activate($this->createComposerMock($rootDir), $io);

        $originalDir = getcwd();
        chdir($unrelatedCwd);

        try {
            $plugin->onPostInstallOrUpdate($this->createEventMock($rootDir));

            $output = $io->getOutput();
            $this->assertStringContainsString('MATE-DISCOVER-RAN', $output);
            $this->assertStringNotContainsString('vendor/bin/mate init', $output);
        } finally {
            chdir($originalDir);
            $this->removeDirectory($rootDir);
        }
    }

    private function createComposerMock(string $rootDir): Composer
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($rootDir.'/vendor');

        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);

        return $composer;
    }

    private function createEventMock(string $rootDir): Event
    {
        $event = $this->createMock(Event::class);
        $event->method('getComposer')->willReturn($this->createComposerMock($rootDir));

        return $event;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}

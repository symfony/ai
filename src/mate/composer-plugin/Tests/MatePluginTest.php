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

use Composer\Script\ScriptEvents;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\ComposerPlugin\MatePlugin;

/**
 * MatePlugin resolves its project root via Composer\InstalledVersions, a process-wide static
 * that already reflects this PHPUnit process' own root (the composer-plugin package itself)
 * before any test runs, and always wins ties over anything reloaded in-process. So every
 * scenario here runs the plugin in a fresh PHP subprocess against a throwaway fixture project,
 * rather than mocking Composer/Config, which the plugin no longer consults for its root.
 *
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
        $fixtureRoot = $this->createFixtureProject();

        try {
            $output = $this->runPluginInFixture($fixtureRoot, $fixtureRoot);

            $this->assertStringContainsString('vendor/bin/mate init', $output);
        } finally {
            $this->removeDirectory($fixtureRoot);
        }
    }

    public function testSkipsWhenMateBinaryDoesNotExist()
    {
        $fixtureRoot = $this->createFixtureProject();

        try {
            mkdir($fixtureRoot.'/mate', 0755, true);
            file_put_contents($fixtureRoot.'/mate/extensions.php', "<?php\nreturn [];\n");

            $output = $this->runPluginInFixture($fixtureRoot, $fixtureRoot);

            $this->assertStringNotContainsString('MATE-DISCOVER-RAN', $output);
            $this->assertStringNotContainsString('vendor/bin/mate init', $output);
        } finally {
            $this->removeDirectory($fixtureRoot);
        }
    }

    /**
     * Regression test for the bug where the plugin trusted getcwd() to find the project root.
     * Composer's "vendor-dir" config is not guaranteed to sit directly under the project root
     * either (a monorepo, CI running from a subfolder, `composer --working-dir=...`, or a
     * "vendor-dir" pointed outside the project entirely, see #1857), so the plugin derives the
     * root from Composer\InstalledVersions::getRootPackage() instead, which always resolves to
     * wherever the root composer.json actually lives.
     */
    public function testResolvesRootIndependentlyOfCurrentWorkingDirectory()
    {
        $fixtureRoot = $this->createFixtureProject();

        try {
            mkdir($fixtureRoot.'/mate', 0755, true);
            file_put_contents($fixtureRoot.'/mate/extensions.php', "<?php\nreturn [];\n");
            file_put_contents($fixtureRoot.'/vendor/bin/mate', "#!/usr/bin/env php\n<?php\necho 'MATE-DISCOVER-RAN';\n");

            // Simulates the real-world shape from the bug report: Composer is invoked while the
            // shell's cwd is nested somewhere below the actual project root, e.g. a Symfony
            // project fixture nested inside an outer monorepo directory.
            $unrelatedCwd = $fixtureRoot.'/nested/unrelated/cwd';
            mkdir($unrelatedCwd, 0755, true);

            $output = $this->runPluginInFixture($fixtureRoot, $unrelatedCwd);

            $this->assertStringContainsString('MATE-DISCOVER-RAN', $output);
            $this->assertStringNotContainsString('vendor/bin/mate init', $output);
        } finally {
            $this->removeDirectory($fixtureRoot);
        }
    }

    /**
     * Builds a throwaway project requiring only composer/composer, so the fixture's own
     * generated autoloader is the first (and only) one InstalledVersions ever sees in the
     * subprocess that runs against it.
     */
    private function createFixtureProject(): string
    {
        $fixtureRoot = sys_get_temp_dir().'/mate-plugin-test-'.uniqid();
        mkdir($fixtureRoot, 0755, true);

        file_put_contents($fixtureRoot.'/composer.json', json_encode([
            'name' => 'fixture/mate-plugin-root-detection',
            'require' => ['composer/composer' => '^2'],
        ]));

        $process = proc_open(
            ['composer', 'install', '--no-interaction', '--no-progress', '--no-scripts', '-q'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $fixtureRoot,
        );
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (0 !== $exitCode) {
            $this->removeDirectory($fixtureRoot);
            $this->fail('Could not install the composer/composer fixture dependency.');
        }

        return $fixtureRoot;
    }

    private function runPluginInFixture(string $fixtureRoot, string $cwd): string
    {
        $runner = $fixtureRoot.'/run-plugin.php';
        $pluginSource = realpath(__DIR__.'/../src/MatePlugin.php');

        file_put_contents($runner, <<<PHP
            <?php
            require '{$fixtureRoot}/vendor/autoload.php';
            require '{$pluginSource}';

            \$io = new Composer\IO\BufferIO();
            \$plugin = new Symfony\AI\Mate\ComposerPlugin\MatePlugin();
            \$plugin->activate(new Composer\Composer(), \$io);
            \$plugin->onPostInstallOrUpdate(new Composer\Script\Event('post-install-cmd', new Composer\Composer(), \$io));

            echo \$io->getOutput();
            PHP);

        $process = proc_open(
            [\PHP_BINARY, $runner],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwd,
        );

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $output;
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

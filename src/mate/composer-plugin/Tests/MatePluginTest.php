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
 * Every scenario runs the plugin in a fresh PHP subprocess against a throwaway fixture project:
 * the root is resolved from Composer\Factory::getComposerFile(), which depends on the process'
 * working directory and COMPOSER env var, and the autoloader that process loads decides what
 * Composer\InstalledVersions describes.
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
     * Inside a real Composer run, the autoloader is Composer's own: InstalledVersions describes
     * composer/composer, not the project. The plugin must still find the project from the
     * working directory Composer moved into.
     */
    public function testResolvesRootWhenAutoloaderBelongsToAnotherPackage()
    {
        $hostRoot = $this->createFixtureProject();
        $projectRoot = $this->createInitializedProject();

        try {
            $output = $this->runPluginInFixture($hostRoot, $projectRoot);

            $this->assertStringContainsString('MATE-DISCOVER-RAN', $output);
            $this->assertStringNotContainsString('vendor/bin/mate init', $output);
        } finally {
            $this->removeDirectory($hostRoot);
            $this->removeDirectory($projectRoot);
        }
    }

    /**
     * Composer can be pointed at a composer.json outside the working directory with the
     * COMPOSER env var, without changing directory: the root follows that file, not getcwd().
     */
    public function testResolvesRootFromComposerEnvironmentVariable()
    {
        $hostRoot = $this->createFixtureProject();
        $projectRoot = $this->createInitializedProject();

        try {
            $unrelatedCwd = $hostRoot.'/nested/unrelated/cwd';
            mkdir($unrelatedCwd, 0755, true);

            $output = $this->runPluginInFixture($hostRoot, $unrelatedCwd, $projectRoot.'/composer.json');

            $this->assertStringContainsString('MATE-DISCOVER-RAN', $output);
            $this->assertStringNotContainsString('vendor/bin/mate init', $output);
        } finally {
            $this->removeDirectory($hostRoot);
            $this->removeDirectory($projectRoot);
        }
    }

    /**
     * An initialized project with a fake mate binary; it needs no installed dependencies, the
     * plugin only looks for mate/extensions.php and vendor/bin/mate under the resolved root.
     */
    private function createInitializedProject(): string
    {
        $projectRoot = sys_get_temp_dir().'/mate-plugin-project-'.uniqid();
        mkdir($projectRoot.'/mate', 0755, true);
        mkdir($projectRoot.'/vendor/bin', 0755, true);

        file_put_contents($projectRoot.'/composer.json', json_encode(['name' => 'fixture/mate-plugin-project']));
        file_put_contents($projectRoot.'/mate/extensions.php', "<?php\nreturn [];\n");
        file_put_contents($projectRoot.'/vendor/bin/mate', "#!/usr/bin/env php\n<?php\necho 'MATE-DISCOVER-RAN';\n");

        return $projectRoot;
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

    private function runPluginInFixture(string $fixtureRoot, string $cwd, ?string $composerFile = null): string
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

        $env = getenv();
        unset($env['COMPOSER']);
        if (null !== $composerFile) {
            $env['COMPOSER'] = $composerFile;
        }

        $process = proc_open(
            [\PHP_BINARY, $runner],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwd,
            $env,
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

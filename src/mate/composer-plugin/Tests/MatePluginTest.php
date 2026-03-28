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
        $composer = $this->createMock(Composer::class);

        $plugin = new MatePlugin();
        $plugin->activate($composer, $io);

        $originalDir = getcwd();
        $tempDir = sys_get_temp_dir().'/mate-plugin-test-'.uniqid();
        mkdir($tempDir, 0755, true);
        chdir($tempDir);

        try {
            $event = $this->createMock(Event::class);
            $plugin->onPostInstallOrUpdate($event);

            $output = $io->getOutput();
            $this->assertStringContainsString('vendor/bin/mate init', $output);
        } finally {
            chdir($originalDir);
            rmdir($tempDir);
        }
    }

    public function testSkipsWhenMateBinaryDoesNotExist()
    {
        $io = new BufferIO();
        $composer = $this->createMock(Composer::class);

        $plugin = new MatePlugin();
        $plugin->activate($composer, $io);

        $originalDir = getcwd();
        $tempDir = sys_get_temp_dir().'/mate-plugin-test-'.uniqid();
        mkdir($tempDir.'/mate', 0755, true);
        file_put_contents($tempDir.'/mate/extensions.php', "<?php\nreturn [];\n");
        chdir($tempDir);

        try {
            $event = $this->createMock(Event::class);
            $plugin->onPostInstallOrUpdate($event);

            $output = $io->getOutput();
            $this->assertStringNotContainsString('Discovering extensions', $output);
        } finally {
            chdir($originalDir);
            unlink($tempDir.'/mate/extensions.php');
            rmdir($tempDir.'/mate');
            rmdir($tempDir);
        }
    }
}

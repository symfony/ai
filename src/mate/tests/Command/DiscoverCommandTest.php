<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Command;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Command\DiscoverCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class DiscoverCommandTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/../Discovery/Fixtures';
    }

    public function testDiscoversExtensionsAndCreatesFile()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-ai-mate-config', $tempDir);
            $command = new DiscoverCommand($rootDir, new NullLogger());
            $tester = new CommandTester($command);

            $tester->execute([]);

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
            $this->assertFileExists($tempDir.'/.mate/extensions.php');

            $extensions = include $tempDir.'/.mate/extensions.php';
            $this->assertIsArray($extensions);
            $this->assertArrayHasKey('vendor/package-a', $extensions);
            $this->assertArrayHasKey('vendor/package-b', $extensions);
            $this->assertIsArray($extensions['vendor/package-a']);
            $this->assertIsArray($extensions['vendor/package-b']);
            $this->assertTrue($extensions['vendor/package-a']['enabled']);
            $this->assertTrue($extensions['vendor/package-b']['enabled']);

            $output = $tester->getDisplay();
            $this->assertStringContainsString('Discovered 2 Extension', $output);
            $this->assertStringContainsString('vendor/package-a', $output);
            $this->assertStringContainsString('vendor/package-b', $output);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testPreservesExistingEnabledState()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir.'/.mate', 0755, true);

        try {
            // Create existing extensions.php with package-a disabled
            file_put_contents($tempDir.'/.mate/extensions.php', <<<'PHP'
<?php
return [
    'vendor/package-a' => ['enabled' => false],
    'vendor/package-b' => ['enabled' => true],
];
PHP
            );

            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-ai-mate-config', $tempDir);
            $command = new DiscoverCommand($rootDir, new NullLogger());
            $tester = new CommandTester($command);

            $tester->execute([]);

            $extensions = include $tempDir.'/.mate/extensions.php';
            $this->assertIsArray($extensions);
            $this->assertIsArray($extensions['vendor/package-a']);
            $this->assertIsArray($extensions['vendor/package-b']);
            $this->assertFalse($extensions['vendor/package-a']['enabled'], 'Should preserve disabled state');
            $this->assertTrue($extensions['vendor/package-b']['enabled'], 'Should preserve enabled state');
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testNewPackagesDefaultToEnabled()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir.'/.mate', 0755, true);

        try {
            // Create existing extensions.php with only package-a
            file_put_contents($tempDir.'/.mate/extensions.php', <<<'PHP'
<?php
return [
    'vendor/package-a' => ['enabled' => false],
];
PHP
            );

            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-ai-mate-config', $tempDir);
            $command = new DiscoverCommand($rootDir, new NullLogger());
            $tester = new CommandTester($command);

            $tester->execute([]);

            $extensions = include $tempDir.'/.mate/extensions.php';
            $this->assertIsArray($extensions);
            $this->assertIsArray($extensions['vendor/package-a']);
            $this->assertIsArray($extensions['vendor/package-b']);
            $this->assertFalse($extensions['vendor/package-a']['enabled'], 'Existing disabled state preserved');
            $this->assertTrue($extensions['vendor/package-b']['enabled'], 'New package defaults to enabled');
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testDisplaysWarningWhenNoExtensionsFound()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $rootDir = $this->createConfiguration($this->fixturesDir.'/without-ai-mate-config', $tempDir);
            $command = new DiscoverCommand($rootDir, new NullLogger());
            $tester = new CommandTester($command);

            $tester->execute([]);

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

            $output = $tester->getDisplay();
            $this->assertStringContainsString('No MCP extensions found', $output);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    private function createConfiguration(string $rootDir, string $tempDir): string
    {
        // Copy fixture to temp directory for testing
        $this->copyDirectory($rootDir.'/vendor', $tempDir.'/vendor');

        return $tempDir;
    }

    private function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }

        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $files = array_diff(scandir($src) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $srcPath = $src.'/'.$file;
            $dstPath = $dst.'/'.$file;

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

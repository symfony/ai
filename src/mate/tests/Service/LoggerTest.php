<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Exception\FileWriteException;
use Symfony\AI\Mate\Service\Logger;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class LoggerTest extends TestCase
{
    private string $tempDir;
    private string $logFile;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/mate-logger-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->logFile = $this->tempDir.'/test.log';

        // Clear environment variables
        unset($_SERVER['MATE_DEBUG_FILE']);
        unset($_SERVER['MATE_DEBUG_LOG_FILE']);
        unset($_SERVER['MATE_DEBUG']);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        // Clear environment variables
        unset($_SERVER['MATE_DEBUG_FILE']);
        unset($_SERVER['MATE_DEBUG_LOG_FILE']);
        unset($_SERVER['MATE_DEBUG']);
    }

    public function testSuccessfulLogWrite()
    {
        $logger = new Logger($this->logFile, fileLogEnabled: true);
        $logger->info('Test message');

        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Test message', $content);
        $this->assertStringContainsString('[INFO]', $content);
    }

    public function testConfigurableLogPath()
    {
        $customLogFile = $this->tempDir.'/custom.log';
        $logger = new Logger($customLogFile, fileLogEnabled: true);
        $logger->warning('Custom path message');

        $this->assertFileExists($customLogFile);
        $content = file_get_contents($customLogFile);
        $this->assertStringContainsString('Custom path message', $content);
        $this->assertStringContainsString('[WARNING]', $content);
    }

    public function testParametersCanOverrideDefaults()
    {
        $customLogFile = $this->tempDir.'/custom.log';

        $logger = new Logger($customLogFile, fileLogEnabled: true, debugEnabled: true);
        $logger->error('Custom parameters message');

        $this->assertFileExists($customLogFile);
        $content = file_get_contents($customLogFile);
        $this->assertStringContainsString('Custom parameters message', $content);
        $this->assertStringContainsString('[ERROR]', $content);
    }

    public function testFileWriteFailureThrowsException()
    {
        $invalidPath = '/invalid/path/that/does/not/exist/test.log';
        $logger = new Logger($invalidPath, fileLogEnabled: true);

        $this->expectException(FileWriteException::class);
        $this->expectExceptionMessage('Failed to write to log file: "/invalid/path/that/does/not/exist/test.log"');

        $logger->info('This should fail');
    }

    public function testDefaultBehaviorWritesToStderr()
    {
        // When fileLogEnabled is false and STDERR is defined, it should write to STDERR
        // We can't easily test STDERR output, but we can verify no file is created

        $logger = new Logger($this->logFile, fileLogEnabled: false);
        $logger->info('STDERR message');

        $this->assertFileDoesNotExist($this->logFile);
    }

    public function testDebugMessagesRespectDebugFlag()
    {
        $logger = new Logger($this->logFile, fileLogEnabled: true, debugEnabled: false);
        $logger->debug('Debug message without flag');

        // Without debugEnabled, debug messages should not be logged
        $this->assertFileDoesNotExist($this->logFile);

        $logger = new Logger($this->logFile, fileLogEnabled: true, debugEnabled: true);
        $logger->debug('Debug message with flag');

        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Debug message with flag', $content);
        $this->assertStringNotContainsString('Debug message without flag', $content);
    }

    public function testContextIsIncludedWithDebugMode()
    {
        $logger = new Logger($this->logFile, fileLogEnabled: true, debugEnabled: true);
        $context = ['user' => 'john', 'action' => 'login'];
        $logger->info('User action', $context);

        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('User action', $content);
        $this->assertStringContainsString('"user":"john"', $content);
        $this->assertStringContainsString('"action":"login"', $content);
    }

    public function testContextIsNotIncludedWithoutDebugMode()
    {
        $logger = new Logger($this->logFile, fileLogEnabled: true, debugEnabled: false);
        $context = ['user' => 'john', 'action' => 'login'];
        $logger->info('User action', $context);

        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('User action', $content);
        $this->assertStringNotContainsString('"user":"john"', $content);
    }

    public function testMultipleMessagesAppendToFile()
    {
        $logger = new Logger($this->logFile, fileLogEnabled: true);
        $logger->info('First message');
        $logger->warning('Second message');
        $logger->error('Third message');

        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('First message', $content);
        $this->assertStringContainsString('Second message', $content);
        $this->assertStringContainsString('Third message', $content);
    }

    public function testLogMessageWithStringableLevel()
    {
        $logger = new Logger($this->logFile, fileLogEnabled: true);
        $stringableLevel = new class implements \Stringable {
            public function __toString(): string
            {
                return 'custom';
            }
        };

        $logger->log($stringableLevel, 'Stringable level message');

        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('[CUSTOM]', $content);
        $this->assertStringContainsString('Stringable level message', $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

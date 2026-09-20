<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Container;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Container\ContainerFactory;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ContainerFactoryTest extends TestCase
{
    private Filesystem $filesystem;
    private string $rootDir;
    private string $otherDir;
    private string $previousWorkingDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->rootDir = sys_get_temp_dir().'/mate-container-factory-root-'.uniqid();
        $this->otherDir = sys_get_temp_dir().'/mate-container-factory-other-'.uniqid();
        $this->filesystem->mkdir([$this->rootDir, $this->otherDir]);

        // Mate is usually started from a wrapper or a subdirectory, so the working directory must not matter
        $this->previousWorkingDir = (string) getcwd();
        chdir($this->otherDir);

        $_SERVER['MATE_DEBUG_FILE'] = '1';
        unset($_SERVER['MATE_DEBUG_LOG_FILE']);
    }

    protected function tearDown(): void
    {
        chdir($this->previousWorkingDir);
        $this->filesystem->remove([$this->rootDir, $this->otherDir]);

        unset($_SERVER['MATE_DEBUG_FILE'], $_SERVER['MATE_DEBUG_LOG_FILE']);
    }

    public function testDefaultDebugLogFileIsWrittenToTheProjectRoot()
    {
        $this->logThroughContainer();

        $this->assertStringContainsString('Runtime message', (string) file_get_contents($this->rootDir.'/dev.log'));
        $this->assertFileDoesNotExist($this->otherDir.'/dev.log');
    }

    public function testRelativeDebugLogFileIsResolvedAgainstTheProjectRoot()
    {
        $_SERVER['MATE_DEBUG_LOG_FILE'] = 'var/log/mate.log';

        $this->logThroughContainer();

        $this->assertStringContainsString('Runtime message', (string) file_get_contents($this->rootDir.'/var/log/mate.log'));
        $this->assertDirectoryDoesNotExist($this->otherDir.'/var');
    }

    public function testAbsoluteDebugLogFileIsKeptAsIs()
    {
        $_SERVER['MATE_DEBUG_LOG_FILE'] = $this->otherDir.'/logs/mate.log';

        $this->logThroughContainer();

        $this->assertStringContainsString('Runtime message', (string) file_get_contents($this->otherDir.'/logs/mate.log'));
        $this->assertSame([], glob($this->rootDir.'/*'));
    }

    public function testDebugLogFileOverriddenInUserConfigIsResolvedAgainstTheProjectRoot()
    {
        $this->filesystem->dumpFile($this->rootDir.'/composer.json', json_encode([
            'extra' => ['ai-mate' => ['extension' => false, 'includes' => ['mate/config.php']]],
        ], \JSON_THROW_ON_ERROR));
        $this->filesystem->dumpFile($this->rootDir.'/mate/config.php', <<<'PHP'
            <?php

            use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

            return static function (ContainerConfigurator $container): void {
                $container->parameters()->set('mate.debug_log_file', 'custom/mate.log');
            };
            PHP);

        $this->logThroughContainer();

        $this->assertStringContainsString('Runtime message', (string) file_get_contents($this->rootDir.'/custom/mate.log'));
        $this->assertDirectoryDoesNotExist($this->otherDir.'/custom');
    }

    private function logThroughContainer(): void
    {
        $logger = (new ContainerFactory($this->rootDir))->create()->get(LoggerInterface::class);
        $this->assertInstanceOf(LoggerInterface::class, $logger);

        $logger->info('Runtime message');
    }
}

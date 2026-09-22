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
use Symfony\AI\Mate\Container\ContainerFactory;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ContainerFactoryEnvFileTest extends TestCase
{
    private const VARIABLES = ['MATE_TEST_SHARED', 'MATE_TEST_LOCAL'];

    private Filesystem $filesystem;
    private string $rootDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->rootDir = sys_get_temp_dir().'/mate-container-factory-env-'.uniqid();
        $this->filesystem->mkdir($this->rootDir);

        $this->filesystem->dumpFile($this->rootDir.'/composer.json', json_encode([
            'extra' => ['ai-mate' => ['extension' => false, 'includes' => ['mate/config.php']]],
        ], \JSON_THROW_ON_ERROR));

        $this->clearVariables();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->rootDir);
        $this->clearVariables();
    }

    public function testNoEnvFileIsLoadedByDefault()
    {
        $this->dumpConfig(null);
        $this->filesystem->dumpFile($this->rootDir.'/mate/.env', "MATE_TEST_SHARED=from-env\n");

        (new ContainerFactory($this->rootDir))->create();

        $this->assertArrayNotHasKey('MATE_TEST_SHARED', $_ENV);
    }

    public function testConfiguredEnvFileIsLoadedTogetherWithItsLocalOverride()
    {
        $this->dumpConfig('mate/.env');
        $this->filesystem->dumpFile($this->rootDir.'/mate/.env', "MATE_TEST_SHARED=from-env\nMATE_TEST_LOCAL=from-env\n");
        $this->filesystem->dumpFile($this->rootDir.'/mate/.env.local', "MATE_TEST_LOCAL=from-local\n");

        (new ContainerFactory($this->rootDir))->create();

        $this->assertSame('from-env', $_ENV['MATE_TEST_SHARED']);
        $this->assertSame('from-local', $_ENV['MATE_TEST_LOCAL']);
    }

    private function dumpConfig(?string $envFile): void
    {
        $this->filesystem->dumpFile($this->rootDir.'/mate/config.php', \sprintf(<<<'PHP'
            <?php

            use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

            return static function (ContainerConfigurator $container): void {
                $container->parameters()->set('mate.env_file', %s);
            };
            PHP, var_export($envFile, true)));
    }

    private function clearVariables(): void
    {
        foreach (self::VARIABLES as $variable) {
            unset($_ENV[$variable], $_SERVER[$variable]);
            putenv($variable);
        }

        unset($_SERVER['SYMFONY_DOTENV_VARS'], $_ENV['SYMFONY_DOTENV_VARS']);
        putenv('SYMFONY_DOTENV_VARS');
    }
}

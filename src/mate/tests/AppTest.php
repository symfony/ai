<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\App;
use Symfony\AI\Mate\Container\ContainerFactory;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Covers the path a user actually takes: container, application, command name.
 *
 * Registering a command in default.config.php does not put it on the console.
 * App::build() adds commands from a hard-coded list, so a command can be fully
 * wired, fully unit-tested, and still answer "Command is not defined" when
 * anyone runs it. That is what happened to tools:call-batch.
 */
final class AppTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/mate-app-test-'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/vendor', 0o777, true);
        file_put_contents($this->projectDir.'/composer.json', '{"name":"test/project","require":{}}');
        file_put_contents($this->projectDir.'/vendor/autoload.php', '<?php return [];');
    }

    protected function tearDown(): void
    {
        @unlink($this->projectDir.'/vendor/autoload.php');
        @unlink($this->projectDir.'/composer.json');
        @rmdir($this->projectDir.'/vendor');
        @rmdir($this->projectDir);
    }

    public function testTheBatchCommandIsReachableByName(): void
    {
        $application = App::build((new ContainerFactory($this->projectDir))->create());

        $this->assertTrue(
            $application->has('tools:call-batch'),
            'tools:call-batch must be on the console, not merely registered in the container.',
        );
    }

    /**
     * The general form of the same bug. Any command added to default.config.php
     * and forgotten in App::build() fails here rather than silently at runtime.
     */
    public function testEveryRegisteredCommandReachesTheConsole(): void
    {
        $application = App::build((new ContainerFactory($this->projectDir))->create());

        $onConsole = [];
        foreach ($application->all() as $command) {
            $onConsole[] = $command::class;
        }

        $missing = array_values(array_diff($this->registeredCommandClasses(), $onConsole));

        $this->assertSame(
            [],
            $missing,
            'These commands are registered as services but never added to the application: '.implode(', ', $missing),
        );
    }

    /**
     * @return list<class-string>
     */
    private function registeredCommandClasses(): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $this->projectDir);
        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/src')))->load('default.config.php');

        $commands = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            // `$services->set(Foo::class)` leaves the class implicit, so the
            // service id is the only place the class name appears.
            $class = $definition->getClass() ?? $id;
            if (class_exists($class) && is_a($class, Command::class, true)) {
                $commands[] = $class;
            }
        }

        $this->assertNotEmpty($commands, 'no commands found in default.config.php; the test is not testing anything');

        return $commands;
    }
}

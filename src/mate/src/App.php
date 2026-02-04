<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate;

use Mcp\Server\Transport\Stdio\RunnerControl;
use Mcp\Server\Transport\Stdio\RunnerState;
use Symfony\AI\Mate\Command\ClearCacheCommand;
use Symfony\AI\Mate\Command\DebugCapabilitiesCommand;
use Symfony\AI\Mate\Command\DebugExtensionsCommand;
use Symfony\AI\Mate\Command\DiscoverCommand;
use Symfony\AI\Mate\Command\InitCommand;
use Symfony\AI\Mate\Command\ServeCommand;
use Symfony\AI\Mate\Command\StopCommand;
use Symfony\AI\Mate\Command\ToolsCallCommand;
use Symfony\AI\Mate\Command\ToolsInspectCommand;
use Symfony\AI\Mate\Command\ToolsListCommand;
use Symfony\AI\Mate\Exception\UnsupportedVersionException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class App
{
    public const NAME = 'Symfony AI Mate';
    public const VERSION = '0.3.0';

    public static function build(ContainerInterface $container): Application
    {
        $application = new Application(self::NAME, self::VERSION);

        $commands = [
            InitCommand::class,
            ServeCommand::class,
            DiscoverCommand::class,
            StopCommand::class,
            DebugCapabilitiesCommand::class,
            DebugExtensionsCommand::class,
            ClearCacheCommand::class,
            ToolsListCommand::class,
            ToolsInspectCommand::class,
            ToolsCallCommand::class,
        ];

        foreach ($commands as $commandClass) {
            $command = $container->get($commandClass);
            \assert(
                $command instanceof Command,
                \sprintf('Service %s is not an instance of %s', $commandClass, Command::class),
            );

            self::addCommand($application, $command);
        }

        if (\defined('SIGUSR1') && class_exists(RunnerControl::class)) {
            $application->getSignalRegistry()->register(\SIGUSR1, static function () {
                RunnerControl::$state = RunnerState::STOP;
            });
        }

        return $application;
    }

    /**
     * Add commands in a way that works with all support symfony/console versions.
     */
    private static function addCommand(Application $application, Command $command): void
    {
        // @phpstan-ignore function.alreadyNarrowedType
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } elseif (method_exists($application, 'add')) {
            $application->add($command);
        } else {
            throw new UnsupportedVersionException('Unsupported version of symfony/console. We cannot add commands.');
        }
    }
}

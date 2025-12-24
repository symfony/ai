<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Stop all running servers. This will force the AI to restart the server. Can be combined with
 * the "--force-keep-alive" option on the "serve" command to make sure the server is restarted
 * and not killed.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
#[AsCommand('stop', 'Stop running servers to allow them to be restarted with new configuration')]
class StopCommand extends Command
{
    public function __construct(private string $cacheDir)
    {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'stop';
    }

    public static function getDefaultDescription(): string
    {
        return 'Stop running servers to allow them to be restarted with new configuration';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\function_exists('posix_kill')) {
            $io->error('The "stop" command require the posix php extension.');

            return Command::FAILURE;
        }

        if (!\defined('SIGUSR1')) {
            $io->error('The "stop" command require the pcntl php extension.');

            return Command::FAILURE;
        }

        $finder = new Finder();
        $finder->files()
            ->in($this->cacheDir)
            ->name('server_*.pid');

        foreach ($finder as $file) {
            $pid = (int) file_get_contents($file->getRealPath());
            posix_kill($pid, \SIGUSR1);
        }

        return Command::SUCCESS;
    }
}

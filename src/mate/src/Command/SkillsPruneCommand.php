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

use Symfony\AI\Mate\Skill\SkillManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes generated mate-* folders that no longer belong to any skill.
 *
 * "skills:install" already prunes what it knows about; this command exists for the leftovers it
 * cannot see — a folder left behind by an interrupted run, or by hand-editing mate/extensions.php.
 * Only "mate-" prefixed entries are ever touched, so skills you maintain yourself are left alone.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:prune', 'Remove generated skill folders that no longer belong to any skill')]
class SkillsPruneCommand extends Command
{
    public function __construct(
        private SkillManager $manager,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:prune';
    }

    public static function getDefaultDescription(): string
    {
        return 'Remove generated skill folders that no longer belong to any skill';
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be removed without touching the filesystem');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = true === $input->getOption('dry-run');
        $strays = $this->manager->pruneStrays($dryRun);

        if ([] === $strays) {
            $io->success('Nothing to prune.');

            return Command::SUCCESS;
        }

        $io->listing($strays);

        if ($dryRun) {
            $io->note(\sprintf('%d folder(s) would be removed. Re-run without --dry-run to apply.', \count($strays)));

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Removed %d folder(s).', \count($strays)));

        return Command::SUCCESS;
    }
}

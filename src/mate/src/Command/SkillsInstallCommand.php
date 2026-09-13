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

use Symfony\AI\Mate\Skill\Model\SkillInstallResult;
use Symfony\AI\Mate\Skill\SkillManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reconcile the generated skill folders from source + intent.
 *
 * This is the one idempotent reconciler: it rebuilds .agents/skills/ and .claude/skills/ from the
 * vendor sources (or user overrides in mate/skills/), prunes skills of disabled or removed
 * extensions, and records what it did back into mate/extensions.php. It never writes into
 * mate/skills/ (user-owned overrides).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:install', 'Install and reconcile Mate skills into the generated agent folders')]
class SkillsInstallCommand extends Command
{
    public function __construct(
        private SkillManager $manager,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:install';
    }

    public static function getDefaultDescription(): string
    {
        return 'Install and reconcile Mate skills into the generated agent folders';
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> command rebuilds the generated skill folders
(<comment>.agents/skills/</comment> and <comment>.claude/skills/</comment>) from the skills declared
by installed Mate extensions and the root project.

Both the intent it reads (<comment>enabled</comment>, <comment>mode</comment>) and the facts it
records (<comment>state</comment>, <comment>source</comment>, hashes, targets) live in
<comment>mate/extensions.php</comment>. The command is idempotent: running it repeatedly converges on
the same result and never touches your overrides in <comment>mate/skills/</comment>.

Pass <comment>--dry-run</comment> to see what a run would change. The same reconciler runs, it just
writes nothing: no generated folder is touched and <comment>mate/extensions.php</comment> stays as it
is.
HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');

        $this->render($io, $this->manager->reinstall($dryRun), $dryRun);

        return Command::SUCCESS;
    }

    private function render(SymfonyStyle $io, SkillInstallResult $result, bool $dryRun): void
    {
        $io->title($dryRun ? 'Skill Installation (dry run)' : 'Skill Installation');

        if ([] !== $result->installed) {
            $message = \sprintf('%s %d new skill%s: %s', $dryRun ? 'Would install' : 'Installed', \count($result->installed), 1 === \count($result->installed) ? '' : 's', implode(', ', $result->installed));
            if ($dryRun) {
                $io->text($message);
            } else {
                $io->success($message);
            }
        }

        if ([] !== $result->updated) {
            $io->text(\sprintf('%s %d skill%s: %s', $dryRun ? 'Would rebuild' : 'Rebuilt', \count($result->updated), 1 === \count($result->updated) ? '' : 's', implode(', ', $result->updated)));
        }

        if ([] !== $result->removed) {
            $io->text(\sprintf('%s %d skill%s: %s', $dryRun ? 'Would remove' : 'Removed', \count($result->removed), 1 === \count($result->removed) ? '' : 's', implode(', ', $result->removed)));
        }

        foreach ($result->skipped as $name => $reason) {
            $io->warning(\sprintf('%s %s: %s', $dryRun ? 'Would skip' : 'Skipped', $name, $reason));
        }

        foreach ($result->notices as $notice) {
            $io->note($notice);
        }

        if ([] === $result->active) {
            $io->text($dryRun ? 'No skills would be installed.' : 'No skills are currently installed.');

            return;
        }

        $io->text(\sprintf('%d skill%s %s: %s', \count($result->active), 1 === \count($result->active) ? '' : 's', $dryRun ? 'would be installed' : 'installed', implode(', ', $result->active)));

        if ($dryRun && [] === $result->installed && [] === $result->updated && [] === $result->removed) {
            $io->success('Nothing to do, the generated folders are up to date.');
        }
    }
}

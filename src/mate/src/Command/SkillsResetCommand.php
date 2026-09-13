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

use Symfony\AI\Mate\Command\Trait\RendersSkillOutcomeTrait;
use Symfony\AI\Mate\Skill\SkillManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Hands an overridden skill back to Mate.
 *
 * The local copy under mate/skills/ is kept by default and only its path is reported: silently
 * deleting content the user wrote is not something a "reset" should do. Pass --delete-copy to
 * remove it explicitly.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:reset', 'Hand an overridden skill back to Mate')]
class SkillsResetCommand extends Command
{
    use RendersSkillOutcomeTrait;

    public function __construct(
        private SkillManager $manager,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:reset';
    }

    public static function getDefaultDescription(): string
    {
        return 'Hand an overridden skill back to Mate';
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Installed ("mate-…") or original skill name');
        $this->addOption('delete-copy', null, InputOption::VALUE_NONE, 'Also delete your copy under mate/skills/');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> command switches a skill back to
<comment>'mode' => 'managed'</comment>, so Mate builds it from the package again.

Your copy under <comment>mate/skills/&lt;name&gt;/</comment> is left in place and simply stops being
used; pass <comment>--delete-copy</comment> to remove it as well.
HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        \assert(\is_string($name));

        $resolved = $this->resolveSkill($io, $this->manager, $name);
        if (null === $resolved) {
            return Command::FAILURE;
        }

        $statuses = $this->manager->statusFor($resolved['name']);
        if ([] !== $statuses && 'managed' === $statuses[0]->mode) {
            $io->note(\sprintf('Skill "%s" is already managed by Mate; nothing to reset.', $statuses[0]->installedName));

            return Command::SUCCESS;
        }

        $this->manager->setMode($resolved['package'], $resolved['name'], 'managed');
        $this->manager->reinstall();

        $copyPath = $this->manager->overrideCopyPath($resolved['name']);

        if (true === $input->getOption('delete-copy')) {
            if ($this->manager->removeOverrideCopy($resolved['name'])) {
                $io->text(\sprintf('Deleted %s/.', $copyPath));
            }
        } elseif ($this->manager->hasOverrideCopy($resolved['name'])) {
            $io->note(\sprintf('Your copy is kept at %s/ but is no longer used. Delete it yourself, or re-run with --delete-copy.', $copyPath));
        }

        $io->success('The skill is managed by Mate again and was rebuilt from its package.');
        $this->renderSkillRow($io, $this->manager, $resolved['name']);

        return Command::SUCCESS;
    }
}

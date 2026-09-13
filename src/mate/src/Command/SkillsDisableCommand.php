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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Hides a skill from coding agents without uninstalling its package.
 *
 * The generated folders are removed and the entry is recorded as disabled, so the skill stays in
 * mate/extensions.php and can be switched back on later. An override copy under mate/skills/ is
 * left untouched.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:disable', 'Hide a skill from coding agents')]
class SkillsDisableCommand extends Command
{
    use RendersSkillOutcomeTrait;

    public function __construct(
        private SkillManager $manager,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:disable';
    }

    public static function getDefaultDescription(): string
    {
        return 'Hide a skill from coding agents';
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Installed ("mate-…") or original skill name');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> command sets <comment>'enabled' => false</comment> for a skill in
<comment>mate/extensions.php</comment> and removes its generated folders.

The entry stays in the file, so <info>mate skills:enable</info> brings the skill back. A copy of
your own under <comment>mate/skills/</comment> is never touched.
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

        // Compared against the recorded flag, not SkillStatus::$enabled: the latter is already false
        // when only the owning extension is disabled, and the skill itself would stay enabled.
        $statuses = $this->manager->statusFor($resolved['name']);
        if ([] !== $statuses && false === $this->manager->isEnabled($resolved['package'], $resolved['name'])) {
            $io->note(\sprintf('Skill "%s" is already disabled; nothing to disable.', $statuses[0]->installedName));

            return Command::SUCCESS;
        }

        $this->manager->setEnabled($resolved['package'], $resolved['name'], false);
        $this->manager->reinstall();

        $io->success(\sprintf('Skill "%s" is disabled and its generated folders were removed.', $statuses[0]->installedName));
        $this->renderSkillRow($io, $this->manager, $resolved['name']);

        return Command::SUCCESS;
    }
}

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
 * Makes a previously disabled skill visible to coding agents again.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:enable', 'Make a disabled skill visible to coding agents again')]
class SkillsEnableCommand extends Command
{
    use RendersSkillOutcomeTrait;

    public function __construct(
        private SkillManager $manager,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:enable';
    }

    public static function getDefaultDescription(): string
    {
        return 'Make a disabled skill visible to coding agents again';
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Installed ("mate-…") or original skill name');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> command sets <comment>'enabled' => true</comment> for a skill in
<comment>mate/extensions.php</comment> and rebuilds its generated folders.

If the owning extension itself is disabled, the skill stays hidden — enable the extension in
<comment>mate/extensions.php</comment> as well.
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
        if ([] !== $statuses && true === $this->manager->isEnabled($resolved['package'], $resolved['name'])) {
            $io->note(\sprintf('Skill "%s" is already enabled; nothing to enable.', $statuses[0]->installedName));

            // Its own flag is set, so the extension is the only thing left that can hide it. Saying
            // nothing here would leave the user with a no-op and no explanation.
            if (!$statuses[0]->enabled) {
                $this->warnAboutDisabledExtension($io, $statuses[0]->installedName, $resolved['package']);
            }

            return Command::SUCCESS;
        }

        $this->manager->setEnabled($resolved['package'], $resolved['name'], true);
        $this->manager->reinstall();

        $statuses = $this->manager->statusFor($resolved['name']);
        if ([] !== $statuses && !$statuses[0]->enabled) {
            $this->warnAboutDisabledExtension($io, $statuses[0]->installedName, $resolved['package']);
            $this->renderSkillRow($io, $this->manager, $resolved['name']);

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Skill "%s" is enabled and was installed.', $statuses[0]->installedName));
        $this->renderSkillRow($io, $this->manager, $resolved['name']);

        return Command::SUCCESS;
    }

    private function warnAboutDisabledExtension(SymfonyStyle $io, string $installedName, string $package): void
    {
        $io->warning(\sprintf('Skill "%s" stays hidden because the extension "%s" is disabled in mate/extensions.php.', $installedName, $package));
    }
}

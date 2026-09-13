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
use Symfony\AI\Mate\Exception\RuntimeException;
use Symfony\AI\Mate\Skill\SkillManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Takes ownership of a skill by copying the package's version into mate/skills/.
 *
 * The copy keeps the original frontmatter name — the installed "mate-" name is applied at build
 * time, exactly as for a managed skill. From here on the installer builds from your copy and never
 * writes into mate/skills/ again.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:override', 'Take ownership of a skill by copying it into mate/skills/')]
class SkillsOverrideCommand extends Command
{
    use RendersSkillOutcomeTrait;

    public function __construct(
        private SkillManager $manager,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:override';
    }

    public static function getDefaultDescription(): string
    {
        return 'Take ownership of a skill by copying it into mate/skills/';
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Installed ("mate-…") or original skill name');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Replace an existing copy in mate/skills/');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> command copies a skill shipped by a package into
<comment>mate/skills/&lt;name&gt;/</comment> and switches it to <comment>'mode' => 'override'</comment>
in <comment>mate/extensions.php</comment>.

Mate then builds the installed skill from your copy on every run and never writes into
<comment>mate/skills/</comment> again. Use <info>mate skills:reset</info> to hand it back.
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

        $force = true === $input->getOption('force');

        $statuses = $this->manager->statusFor($resolved['name']);
        if ([] !== $statuses && 'override' === $statuses[0]->mode && !$force) {
            $io->error(\sprintf('Skill "%s" is already overridden; your copy is at %s.', $statuses[0]->installedName, $this->manager->overrideCopyPath($resolved['name'])));

            return Command::FAILURE;
        }

        try {
            $path = $this->manager->createOverrideCopy($resolved['package'], $resolved['name'], $force);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->manager->setMode($resolved['package'], $resolved['name'], 'override');
        $this->manager->reinstall();

        $io->success(\sprintf('Copied the skill to %s/ — edit it there and re-run "mate skills:install".', $path));
        $this->renderSkillRow($io, $this->manager, $resolved['name']);

        return Command::SUCCESS;
    }
}

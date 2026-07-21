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

use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Service\SkillsInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Install extension-provided Agent Skills onto the filesystem so coding agents can use them.
 *
 * @phpstan-import-type ExtensionData from ComposerExtensionDiscovery
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:install', 'Install extension skills so coding agents can use them')]
class SkillsInstallCommand extends Command
{
    /**
     * @param string[] $enabledExtensions
     */
    public function __construct(
        private array $enabledExtensions,
        private ComposerExtensionDiscovery $extensionDiscovery,
        private SkillsInstaller $installer,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:install';
    }

    public static function getDefaultDescription(): string
    {
        return 'Install extension skills so coding agents can use them';
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command installs Agent Skills shipped by your installed
Mate extensions so your coding agent (Claude Code, Codex, OpenCode, Copilot, …) can use them.

Each skill is symlinked under a <comment>mate-</comment> prefixed directory into a shared
location (<comment>.agents/skills</comment>, read by Codex, OpenCode and Copilot) and into
<comment>.claude/skills</comment> for Claude Code, so they auto-update with the package (the
link points into the gitignored vendor/ and needs symlink privileges on Windows).

This runs automatically as part of <info>mate discover</info>; use this command for an explicit
re-sync.

  <comment># Install all skills from enabled extensions</comment>
  %command.full_name%
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Mate Skills');

        $result = $this->installer->install($this->collectExtensions());

        if ([] === $result['installed']) {
            $io->note('No new skills to install.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Installed %d skill(s): %s', \count($result['installed']), implode(', ', $result['installed'])));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, ExtensionData>
     */
    private function collectExtensions(): array
    {
        $extensions = [
            '_custom' => $this->extensionDiscovery->discoverRootProject(),
        ];

        foreach ($this->extensionDiscovery->discover($this->enabledExtensions) as $packageName => $data) {
            $extensions[$packageName] = $data;
        }

        return $extensions;
    }
}

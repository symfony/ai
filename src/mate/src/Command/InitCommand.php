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

use HelgeSverre\Toon\Toon;
use Symfony\AI\Mate\Agent\AgentInstructionsMaterializer;
use Symfony\AI\Mate\Service\FilePermissions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Add some config in the project root and scaffold integration helpers.
 * Basically do every thing you need to set things up.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
#[AsCommand('init', 'Initialize Mate configuration and directory structure')]
class InitCommand extends Command
{
    /**
     * Scaffolded files that may contain secrets or local configuration and must not be
     * world-readable.
     *
     * @var list<string>
     */
    private const SENSITIVE_FILES = [
        'mate/.env',
        'mate/config.php',
    ];

    public function __construct(
        private string $rootDir,
        private AgentInstructionsMaterializer $instructionsMaterializer,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'init';
    }

    public static function getDefaultDescription(): string
    {
        return 'Initialize Mate configuration and directory structure';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('AI Mate Initialization');
        $io->text('Setting up AI Mate configuration and directory structure...');
        $io->newLine();

        $actions = [];

        $mateDir = $this->rootDir.'/mate';
        if (!is_dir($mateDir)) {
            mkdir($mateDir, FilePermissions::DIRECTORY, true);
            $actions[] = ['✓', 'Created', 'mate/ directory'];
        }

        $files = [
            'mate/extensions.php',
            'mate/config.php',
            'mate/.env',
            'mate/.gitignore',
            'mate/AGENT_INSTRUCTIONS.md',
        ];
        foreach ($files as $file) {
            $fullPath = $this->rootDir.'/'.$file;
            if (!file_exists($fullPath)) {
                $this->copyTemplate($file, $fullPath);
                $this->postCopyTemplateAction($file, $fullPath);
                $actions[] = ['✓', 'Created', $file];
            } elseif ($io->confirm(\sprintf('<question>%s already exists. Overwrite?</question>', $fullPath), false)) {
                unlink($fullPath);
                $this->copyTemplate($file, $fullPath);
                $this->postCopyTemplateAction($file, $fullPath);
                $actions[] = ['✓', 'Updated', $file];
            } else {
                $actions[] = ['○', 'Skipped', $file.' (already exists)'];
            }
        }

        $mateSrcDir = $this->rootDir.'/mate/src';
        if (!is_dir($mateSrcDir)) {
            mkdir($mateSrcDir, FilePermissions::DIRECTORY, true);
            file_put_contents($mateSrcDir.'/.gitignore', '');
            $actions[] = ['✓', 'Created', 'mate/src/ directory (for custom tools)'];
        } else {
            $actions[] = ['○', 'Exists', 'mate/src/ directory'];
        }

        $composerActions = $this->updateComposerJson();
        $actions = array_merge($actions, $composerActions);

        $materializationResult = $this->instructionsMaterializer->synchronizeFromCurrentInstructionsFile();
        if ($materializationResult['agents_file_updated']) {
            $actions[] = ['✓', 'Updated', 'AGENTS.md (AI Mate managed instructions block)'];
        } else {
            $actions[] = ['⚠', 'Warning', 'Could not update AGENTS.md managed instructions block'];
        }

        $io->section('Summary');
        $io->table(['', 'Action', 'Item'], $actions);

        $io->success('AI Mate initialization complete!');

        $io->comment([
            'Next steps:',
            '  1. Run "composer dump-autoload" to register the Mate\\ autoloader',
            '  2. Add custom tools to mate/src/ (public methods with the #[AsTool] attribute)',
            '  3. Point your coding agent at the CLI — it reads mate/AGENT_INSTRUCTIONS.md and runs',
            '     "vendor/bin/mate tools:list", "tools:inspect <tool>" and "tools:call <tool> --<param>=<value>"',
        ]);

        if (!class_exists(Toon::class)) {
            $io->note('Tip: install "helgesverre/toon" (composer require helgesverre/toon) to reduce tokens in tool responses.');
        }

        return Command::SUCCESS;
    }

    private function copyTemplate(string $template, string $destination): void
    {
        $directory = \dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, FilePermissions::DIRECTORY, true);
        }

        copy(__DIR__.'/../../resources/'.$template, $destination);
    }

    private function postCopyTemplateAction(string $template, string $destination): void
    {
        // Restrict files that may contain secrets or local configuration so they are not
        // readable by other users on shared hosts.
        if (\in_array($template, self::SENSITIVE_FILES, true)) {
            @chmod($destination, FilePermissions::FILE);
        }
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function updateComposerJson(): array
    {
        $composerJsonPath = $this->rootDir.'/composer.json';
        $actions = [];

        if (!file_exists($composerJsonPath)) {
            $actions[] = ['⚠', 'Warning', 'composer.json not found in project root'];

            return $actions;
        }

        $composerContent = file_get_contents($composerJsonPath);
        if (false === $composerContent) {
            $actions[] = ['✗', 'Error', 'Failed to read composer.json'];

            return $actions;
        }

        $composerJson = json_decode($composerContent, true);

        if (\JSON_ERROR_NONE !== json_last_error() || !\is_array($composerJson)) {
            $actions[] = ['✗', 'Error', 'Failed to parse composer.json: '.json_last_error_msg()];

            return $actions;
        }

        $modified = false;

        $composerJson['extra'] = \is_array($composerJson['extra'] ?? null) ? $composerJson['extra'] : [];
        $composerJson['autoload-dev'] = \is_array($composerJson['autoload-dev'] ?? null) ? $composerJson['autoload-dev'] : [];
        $composerJson['autoload-dev']['psr-4'] = \is_array($composerJson['autoload-dev']['psr-4'] ?? null) ? $composerJson['autoload-dev']['psr-4'] : [];

        if (!isset($composerJson['extra']['ai-mate'])) {
            $composerJson['extra']['ai-mate'] = [
                'extension' => false,
                'scan-dirs' => ['mate/src'],
                'includes' => ['mate/config.php'],
            ];
            $modified = true;
            $actions[] = ['✓', 'Added', 'extra.ai-mate configuration'];
        } else {
            $actions[] = ['○', 'Exists', 'extra.ai-mate configuration'];
        }

        if (!isset($composerJson['autoload-dev']['psr-4']['Mate\\'])) {
            $composerJson['autoload-dev']['psr-4']['Mate\\'] = 'mate/src/';
            $modified = true;
            $actions[] = ['✓', 'Added', 'Mate\\ autoloader'];
        } else {
            $actions[] = ['○', 'Exists', 'Mate\\ autoloader'];
        }

        if ($modified) {
            file_put_contents(
                $composerJsonPath,
                json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n"
            );
            $actions[] = ['✓', 'Updated', 'composer.json'];
        }

        return $actions;
    }
}

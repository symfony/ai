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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Add some config in the project root, automatically discover tools.
 * Basically do every thing you need to set things up.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class InitCommand extends Command
{
    public function __construct(
        private string $rootDir,
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
            mkdir($mateDir, 0755, true);
            $actions[] = ['✓', 'Created', 'mate/ directory'];
        }

        $files = ['mate/extensions.php', 'mate/config.php', 'mate/.env', 'mate/.gitignore', 'mcp.json'];
        foreach ($files as $file) {
            $fullPath = $this->rootDir.'/'.$file;
            if (!file_exists($fullPath)) {
                $this->copyTemplate($file, $fullPath);
                $actions[] = ['✓', 'Created', $file];
            } elseif ($io->confirm(\sprintf('<question>%s already exists. Overwrite?</question>', $fullPath), false)) {
                unlink($fullPath);
                $this->copyTemplate($file, $fullPath);
                $actions[] = ['✓', 'Updated', $file];
            } else {
                $actions[] = ['○', 'Skipped', $file.' (already exists)'];
            }
        }

        // Create symlink from .mcp.json to mcp.json for compatibility
        $mcpJsonPath = $this->rootDir.'/mcp.json';
        $mcpJsonSymlink = $this->rootDir.'/.mcp.json';
        if (file_exists($mcpJsonPath)) {
            if (is_link($mcpJsonSymlink)) {
                unlink($mcpJsonSymlink);
            }
            if (!file_exists($mcpJsonSymlink)) {
                symlink('mcp.json', $mcpJsonSymlink);
                $actions[] = ['✓', 'Created', '.mcp.json (symlink to mcp.json)'];
            } elseif ($io->confirm(\sprintf('<question>%s already exists. Replace with symlink?</question>', $mcpJsonSymlink), false)) {
                unlink($mcpJsonSymlink);
                symlink('mcp.json', $mcpJsonSymlink);
                $actions[] = ['✓', 'Updated', '.mcp.json (symlink to mcp.json)'];
            } else {
                $actions[] = ['○', 'Skipped', '.mcp.json (already exists)'];
            }
        }

        $mateSrcDir = $this->rootDir.'/mate/src';
        if (!is_dir($mateSrcDir)) {
            mkdir($mateSrcDir, 0755, true);
            file_put_contents($mateSrcDir.'/.gitkeep', '');
            $actions[] = ['✓', 'Created', 'mate/src/ directory (for custom MCP tools)'];
        } else {
            $actions[] = ['○', 'Exists', 'mate/src/ directory'];
        }

        $composerActions = $this->updateComposerJson();
        $actions = array_merge($actions, $composerActions);

        $io->section('Summary');
        $io->table(['', 'Action', 'Item'], $actions);

        $io->success('AI Mate initialization complete!');

        $io->comment([
            'Next steps:',
            '  1. Run "composer dump-autoload" to update the autoloader',
            '  2. Run "vendor/bin/mate discover" to find MCP extensions',
            '  3. Add your custom MCP tools/resources/prompts to the mate/src/ directory',
            '  4. Run "vendor/bin/mate serve" to start the MCP server',
        ]);

        return Command::SUCCESS;
    }

    private function copyTemplate(string $template, string $destination): void
    {
        copy(__DIR__.'/../../resources/'.$template, $destination);
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
                'scan-dirs' => ['mate'],
                'includes' => ['config.php'],
            ];
            $modified = true;
            $actions[] = ['✓', 'Added', 'extra.ai-mate configuration'];
        } else {
            $actions[] = ['○', 'Exists', 'extra.ai-mate configuration'];
        }

        if (!isset($composerJson['autoload-dev']['psr-4']['App\\Mate\\'])) {
            $composerJson['autoload-dev']['psr-4']['App\\Mate\\'] = 'mate/src/';
            $modified = true;
            $actions[] = ['✓', 'Added', 'App\\Mate\\ autoloader'];
        } else {
            $actions[] = ['○', 'Exists', 'App\\Mate\\ autoloader'];
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

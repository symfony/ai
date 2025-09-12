<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('terminal', 'Tool to run shell commands on this machine')]
final readonly class Shell
{
    public function __construct(
        private bool $askHumanInput = false,
        private int $timeout = 30,
    ) {
    }

    /**
     * @param string|array<int, string> $commands        List of shell commands to run
     * @param bool                      $returnErrOutput Whether to return error output
     */
    public function __invoke(
        string|array $commands,
        bool $returnErrOutput = true,
    ): string {
        // Normalize commands to array
        if (\is_string($commands)) {
            $commands = [$commands];
        }

        if (empty($commands)) {
            return 'Error: No commands provided';
        }

        // Ask for human confirmation if enabled
        if ($this->askHumanInput) {
            $commandString = implode('; ', $commands);
            echo "Executing command:\n {$commandString}\n";
            $userInput = readline('Proceed with command execution? (y/n): ');

            if ('y' !== strtolower($userInput)) {
                return 'Command execution aborted by user.';
            }
        }

        try {
            $results = [];

            foreach ($commands as $command) {
                $result = $this->executeCommand($command, $returnErrOutput);
                $results[] = $result;
            }

            return implode("\n---\n", $results);
        } catch (\Exception $e) {
            return 'Error during command execution: '.$e->getMessage();
        }
    }

    /**
     * Get platform information.
     */
    public function getPlatform(): string
    {
        return match (\PHP_OS_FAMILY) {
            'Darwin' => 'macOS',
            'Windows' => 'Windows',
            'Linux' => 'Linux',
            default => \PHP_OS_FAMILY,
        };
    }

    private function executeCommand(string $command, bool $returnErrOutput): string
    {
        try {
            // Determine the appropriate shell based on the operating system
            $shell = $this->getShellCommand();

            // Use exec with output capture
            $output = [];
            $returnCode = 0;

            if ('cmd' === $shell) {
                // Windows command execution
                $fullCommand = "cmd /c \"{$command}\"";
            } else {
                // Unix-like command execution
                $fullCommand = "{$shell} -c ".escapeshellarg($command);
            }

            exec($fullCommand.' 2>&1', $output, $returnCode);
            $outputString = implode("\n", $output);

            $result = '';
            if (!empty($outputString)) {
                $result .= "STDOUT:\n".$outputString;
            }

            if (0 !== $returnCode) {
                $result .= "\nExit code: ".$returnCode;
            }

            return $result ?: 'Command executed successfully (no output)';
        } catch (\Exception $e) {
            return 'Error executing command: '.$e->getMessage();
        }
    }

    private function getShellCommand(): string
    {
        $os = \PHP_OS_FAMILY;

        return match ($os) {
            'Windows' => 'cmd',
            'Darwin', 'Linux' => '/bin/bash',
            default => '/bin/sh',
        };
    }
}

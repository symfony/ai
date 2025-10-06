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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('packer_validate', 'Tool that validates Packer configuration')]
#[AsTool('packer_build', 'Tool that builds Packer images', method: 'build')]
#[AsTool('packer_fmt', 'Tool that formats Packer configuration', method: 'fmt')]
#[AsTool('packer_fix', 'Tool that fixes Packer configuration', method: 'fix')]
#[AsTool('packer_inspect', 'Tool that inspects Packer configuration', method: 'inspect')]
#[AsTool('packer_version', 'Tool that shows Packer version', method: 'version')]
final readonly class Packer
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $workingDirectory = '.',
        private array $options = [],
    ) {
    }

    /**
     * Validate Packer configuration.
     *
     * @param string $configFile Configuration file path
     * @param string $syntaxOnly Only check syntax
     * @param string $varFile    Variable file path
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     *     warnings: array<int, string>,
     * }|string
     */
    public function __invoke(
        string $configFile = '*.pkr.hcl',
        string $syntaxOnly = 'false',
        string $varFile = '',
    ): array|string {
        try {
            $command = ['packer', 'validate'];

            if ('true' === $syntaxOnly) {
                $command[] = '-syntax-only';
            }

            if ($varFile) {
                $command[] = "-var-file={$varFile}";
            }

            $command[] = $configFile;

            $output = $this->executeCommand($command);

            // Parse warnings from output
            $warnings = [];
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (str_contains($line, 'Warning:')) {
                    $warnings[] = trim($line);
                }
            }

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
                'warnings' => $warnings,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'warnings' => [],
            ];
        }
    }

    /**
     * Build Packer images.
     *
     * @param string $configFile Configuration file path
     * @param string $varFile    Variable file path
     * @param string $var        Variable value (key=value)
     * @param string $only       Only build specified builders
     * @param string $except     Skip specified builders
     * @param string $parallel   Build in parallel
     * @param string $color      Enable color output
     * @param string $debug      Enable debug mode
     * @param string $force      Force build even if artifacts exist
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     *     artifacts: array<int, array{
     *         type: string,
     *         builderId: string,
     *         id: string,
     *         files: array<int, string>,
     *     }>,
     * }|string
     */
    public function build(
        string $configFile = '*.pkr.hcl',
        string $varFile = '',
        string $var = '',
        string $only = '',
        string $except = '',
        string $parallel = 'true',
        string $color = 'true',
        string $debug = 'false',
        string $force = 'false',
    ): array|string {
        try {
            $command = ['packer', 'build'];

            if ($varFile) {
                $command[] = "-var-file={$varFile}";
            }

            if ($var) {
                $command[] = "-var={$var}";
            }

            if ($only) {
                $command[] = "-only={$only}";
            }

            if ($except) {
                $command[] = "-except={$except}";
            }

            if ('false' === $parallel) {
                $command[] = '-parallel=false';
            }

            if ('false' === $color) {
                $command[] = '-color=false';
            }

            if ('true' === $debug) {
                $command[] = '-debug';
            }

            if ('true' === $force) {
                $command[] = '-force';
            }

            $command[] = $configFile;

            $output = $this->executeCommand($command);

            // Parse artifacts from output (simplified)
            $artifacts = [];
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (str_contains($line, 'Build finished') || str_contains($line, 'Artifacts:')) {
                    // In a real implementation, you would parse the actual artifact information
                    // This is a simplified version
                    $artifacts[] = [
                        'type' => 'image',
                        'builderId' => 'unknown',
                        'id' => 'generated',
                        'files' => ['artifact.file'],
                    ];
                }
            }

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
                'artifacts' => $artifacts,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'artifacts' => [],
            ];
        }
    }

    /**
     * Format Packer configuration.
     *
     * @param string $configFile Configuration file path
     * @param string $diff       Show diff instead of modifying files
     * @param string $check      Check if files are formatted
     * @param string $write      Write formatted files
     * @param string $recursive  Process directories recursively
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     *     changed: bool,
     * }|string
     */
    public function fmt(
        string $configFile = '.',
        string $diff = 'false',
        string $check = 'false',
        string $write = 'true',
        string $recursive = 'false',
    ): array|string {
        try {
            $command = ['packer', 'fmt'];

            if ('true' === $diff) {
                $command[] = '-diff';
            }

            if ('true' === $check) {
                $command[] = '-check';
            }

            if ('false' === $write) {
                $command[] = '-write=false';
            }

            if ('true' === $recursive) {
                $command[] = '-recursive';
            }

            $command[] = $configFile;

            $output = $this->executeCommand($command);

            $changed = !str_contains($output, 'No changes');

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
                'changed' => $changed,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'changed' => false,
            ];
        }
    }

    /**
     * Fix Packer configuration.
     *
     * @param string $configFile Configuration file path
     * @param string $write      Write fixed files
     * @param string $diff       Show diff instead of modifying files
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     *     fixed: bool,
     * }|string
     */
    public function fix(
        string $configFile = '.',
        string $write = 'true',
        string $diff = 'false',
    ): array|string {
        try {
            $command = ['packer', 'fix'];

            if ('false' === $write) {
                $command[] = '-write=false';
            }

            if ('true' === $diff) {
                $command[] = '-diff';
            }

            $command[] = $configFile;

            $output = $this->executeCommand($command);

            $fixed = !str_contains($output, 'No fixes needed');

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
                'fixed' => $fixed,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'fixed' => false,
            ];
        }
    }

    /**
     * Inspect Packer configuration.
     *
     * @param string $configFile Configuration file path
     * @param string $varFile    Variable file path
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     *     template: array{
     *         builders: array<int, array{
     *             name: string,
     *             type: string,
     *             description: string,
     *         }>,
     *         provisioners: array<int, array{
     *             type: string,
     *             description: string,
     *         }>,
     *         postProcessors: array<int, array{
     *             type: string,
     *             description: string,
     *         }>,
     *         variables: array<int, array{
     *             name: string,
     *             type: string,
     *             description: string,
     *             default: mixed,
     *         }>,
     *     },
     * }|string
     */
    public function inspect(
        string $configFile = '*.pkr.hcl',
        string $varFile = '',
    ): array|string {
        try {
            $command = ['packer', 'inspect'];

            if ($varFile) {
                $command[] = "-var-file={$varFile}";
            }

            $command[] = $configFile;

            $output = $this->executeCommand($command);

            // Parse template information from output (simplified)
            $template = [
                'builders' => [],
                'provisioners' => [],
                'postProcessors' => [],
                'variables' => [],
            ];

            $lines = explode("\n", $output);
            $currentSection = '';
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_contains($line, 'Builders:')) {
                    $currentSection = 'builders';
                } elseif (str_contains($line, 'Provisioners:')) {
                    $currentSection = 'provisioners';
                } elseif (str_contains($line, 'Post-processors:')) {
                    $currentSection = 'postProcessors';
                } elseif (str_contains($line, 'Variables:')) {
                    $currentSection = 'variables';
                } elseif ($line && !str_contains($line, ':')) {
                    // Add item to current section (simplified parsing)
                    if ('builders' === $currentSection) {
                        $template['builders'][] = [
                            'name' => $line,
                            'type' => 'unknown',
                            'description' => '',
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
                'template' => $template,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'template' => [
                    'builders' => [],
                    'provisioners' => [],
                    'postProcessors' => [],
                    'variables' => [],
                ],
            ];
        }
    }

    /**
     * Show Packer version.
     *
     * @return array{
     *     success: bool,
     *     version: string,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function version(): array|string
    {
        try {
            $command = ['packer', 'version'];

            $output = $this->executeCommand($command);

            // Extract version from output
            $version = 'unknown';
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (preg_match('/Packer v(\d+\.\d+\.\d+)/', $line, $matches)) {
                    $version = $matches[1];
                    break;
                }
            }

            return [
                'success' => true,
                'version' => $version,
                'output' => $output,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'version' => 'unknown',
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute Packer command.
     */
    private function executeCommand(array $command): string
    {
        $commandString = implode(' ', array_map('escapeshellarg', $command));

        $output = [];
        $returnCode = 0;

        exec("cd {$this->workingDirectory} && {$commandString} 2>&1", $output, $returnCode);

        if (0 !== $returnCode) {
            throw new \RuntimeException('Packer command failed: '.implode("\n", $output));
        }

        return implode("\n", $output);
    }
}

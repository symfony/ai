<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\DevAssistantBundle\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Provider for integrating static analysis tools (PHPStan, Psalm, PHP-CS-Fixer).
 *
 * This provider executes and coordinates multiple static analysis tools to provide
 * comprehensive code quality insights that complement AI-powered analysis.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class StaticAnalysisProvider
{
    /**
     * @param array<string, array<string, mixed>> $toolConfigurations
     */
    public function __construct(
        private LoggerInterface $logger,
        private string $projectRoot,
        private array $toolConfigurations = [],
        private int $timeoutSeconds = 60,
    ) {
    }

    /**
     * Analyze a file using configured static analysis tools.
     *
     * @return array<string, array<string, mixed>>
     */
    public function analyze(string $filePath): array
    {
        $this->logger->info('Starting static analysis', [
            'file_path' => $filePath,
            'tools_configured' => array_keys($this->toolConfigurations),
        ]);

        $results = [];

        foreach ($this->toolConfigurations as $tool => $config) {
            if (!($config['enabled'] ?? true)) {
                continue;
            }

            try {
                $results[$tool] = $this->runTool($tool, $filePath, $config);

                $this->logger->info('Static analysis tool completed', [
                    'tool' => $tool,
                    'issues_found' => \count($results[$tool]['issues'] ?? []),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Static analysis tool failed', [
                    'tool' => $tool,
                    'error' => $e->getMessage(),
                    'file_path' => $filePath,
                ]);

                $results[$tool] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'issues' => [],
                ];
            }
        }

        return $results;
    }

    /**
     * Run multiple static analysis tools on a set of files.
     *
     * @param array<string> $filePaths
     *
     * @return array<string, array<string, mixed>>
     */
    public function analyzeMultiple(array $filePaths): array
    {
        $this->logger->info('Starting batch static analysis', [
            'files_count' => \count($filePaths),
            'tools_configured' => array_keys($this->toolConfigurations),
        ]);

        $aggregatedResults = [];

        foreach ($this->toolConfigurations as $tool => $config) {
            if (!($config['enabled'] ?? true)) {
                continue;
            }

            try {
                $aggregatedResults[$tool] = $this->runToolOnMultipleFiles($tool, $filePaths, $config);

                $this->logger->info('Batch static analysis tool completed', [
                    'tool' => $tool,
                    'total_issues' => \count($aggregatedResults[$tool]['issues'] ?? []),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Batch static analysis tool failed', [
                    'tool' => $tool,
                    'error' => $e->getMessage(),
                    'files_count' => \count($filePaths),
                ]);

                $aggregatedResults[$tool] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'issues' => [],
                ];
            }
        }

        return $aggregatedResults;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function runTool(string $tool, string $filePath, array $config): array
    {
        return match ($tool) {
            'phpstan' => $this->runPHPStan($filePath, $config),
            'psalm' => $this->runPsalm($filePath, $config),
            'php_cs_fixer' => $this->runPHPCSFixer($filePath, $config),
            'phpmd' => $this->runPHPMD($filePath, $config),
            'phpcpd' => $this->runPHPCPD($filePath, $config),
            default => throw new \InvalidArgumentException("Unknown static analysis tool: {$tool}"),
        };
    }

    /**
     * @param array<string>        $filePaths
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function runToolOnMultipleFiles(string $tool, array $filePaths, array $config): array
    {
        // For batch analysis, we can optimize by running tools on directories
        $results = ['success' => true, 'issues' => []];

        foreach ($filePaths as $filePath) {
            $fileResult = $this->runTool($tool, $filePath, $config);
            if (isset($fileResult['issues'])) {
                $results['issues'] = array_merge($results['issues'], $fileResult['issues']);
            }
            if (!($fileResult['success'] ?? true)) {
                $results['success'] = false;
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function runPHPStan(string $filePath, array $config): array
    {
        $level = $config['level'] ?? 8;
        $configFile = $config['config_file'] ?? null;

        $command = ['vendor/bin/phpstan', 'analyse', '--no-progress', '--error-format=json'];

        if ($configFile && file_exists($this->projectRoot.'/'.$configFile)) {
            $command[] = '--configuration='.$configFile;
        } else {
            $command[] = '--level='.$level;
        }

        $command[] = $filePath;

        $process = new Process($command, $this->projectRoot, null, null, $this->timeoutSeconds);
        $process->run();

        if ($process->isSuccessful() || 1 === $process->getExitCode()) {
            // Exit code 1 means errors found, which is expected
            $output = $process->getOutput();

            try {
                $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
                $issues = [];

                if (isset($data['files'])) {
                    foreach ($data['files'] as $file => $fileData) {
                        foreach ($fileData['messages'] ?? [] as $message) {
                            $issues[] = [
                                'file' => $file,
                                'line' => $message['line'] ?? null,
                                'message' => $message['message'] ?? '',
                                'severity' => $this->mapPHPStanSeverity($message),
                                'rule' => 'phpstan_level_'.$level,
                                'tool' => 'phpstan',
                                'ignorable' => $message['ignorable'] ?? true,
                            ];
                        }
                    }
                }

                return [
                    'success' => true,
                    'issues' => $issues,
                    'stats' => [
                        'total_errors' => $data['totals']['errors'] ?? 0,
                        'file_errors' => $data['totals']['file_errors'] ?? 0,
                    ],
                ];
            } catch (\JsonException $e) {
                throw new \RuntimeException('Failed to parse PHPStan output: '.$e->getMessage());
            }
        }

        throw new ProcessFailedException($process);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function runPsalm(string $filePath, array $config): array
    {
        $configFile = $config['config_file'] ?? 'psalm.xml';

        $command = ['vendor/bin/psalm', '--output-format=json', '--no-progress'];

        if (file_exists($this->projectRoot.'/'.$configFile)) {
            $command[] = '--config='.$configFile;
        }

        $command[] = $filePath;

        $process = new Process($command, $this->projectRoot, null, null, $this->timeoutSeconds);
        $process->run();

        if ($process->isSuccessful() || 1 === $process->getExitCode()) {
            $output = $process->getOutput();

            try {
                $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
                $issues = [];

                foreach ($data as $issue) {
                    $issues[] = [
                        'file' => $issue['file_name'] ?? $filePath,
                        'line' => $issue['line_from'] ?? null,
                        'column' => $issue['column_from'] ?? null,
                        'message' => $issue['message'] ?? '',
                        'severity' => $this->mapPsalmSeverity($issue['severity'] ?? 'error'),
                        'rule' => $issue['type'] ?? 'psalm_check',
                        'tool' => 'psalm',
                        'snippet' => $issue['snippet'] ?? null,
                    ];
                }

                return [
                    'success' => true,
                    'issues' => $issues,
                ];
            } catch (\JsonException $e) {
                throw new \RuntimeException('Failed to parse Psalm output: '.$e->getMessage());
            }
        }

        throw new ProcessFailedException($process);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function runPHPCSFixer(string $filePath, array $config): array
    {
        $rules = $config['rules'] ?? '@PSR12';
        $configFile = $config['config_file'] ?? null;

        $command = ['vendor/bin/php-cs-fixer', 'fix', '--dry-run', '--format=json', '--diff'];

        if ($configFile && file_exists($this->projectRoot.'/'.$configFile)) {
            $command[] = '--config='.$configFile;
        } else {
            $command[] = '--rules='.$rules;
        }

        $command[] = $filePath;

        $process = new Process($command, $this->projectRoot, null, null, $this->timeoutSeconds);
        $process->run();

        // PHP-CS-Fixer returns exit code 8 when there are fixable issues
        if ($process->isSuccessful() || 8 === $process->getExitCode()) {
            $output = $process->getOutput();

            try {
                $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
                $issues = [];

                foreach ($data['files'] ?? [] as $fileData) {
                    foreach ($fileData['appliedFixers'] ?? [] as $fixer) {
                        $issues[] = [
                            'file' => $fileData['name'],
                            'message' => "Code style issue fixed by: {$fixer}",
                            'severity' => 'medium',
                            'rule' => $fixer,
                            'tool' => 'php-cs-fixer',
                            'fixable' => true,
                            'diff' => $fileData['diff'] ?? null,
                        ];
                    }
                }

                return [
                    'success' => true,
                    'issues' => $issues,
                ];
            } catch (\JsonException $e) {
                // If JSON parsing fails, try to extract information from text output
                $lines = explode("\n", $process->getOutput());
                $issues = [];

                foreach ($lines as $line) {
                    if (str_contains($line, 'Fixed ') || str_contains($line, '1)')) {
                        $issues[] = [
                            'file' => $filePath,
                            'message' => trim($line),
                            'severity' => 'medium',
                            'rule' => 'coding_standards',
                            'tool' => 'php-cs-fixer',
                            'fixable' => true,
                        ];
                    }
                }

                return [
                    'success' => true,
                    'issues' => $issues,
                ];
            }
        }

        throw new ProcessFailedException($process);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function runPHPMD(string $filePath, array $config): array
    {
        $rules = $config['rules'] ?? 'cleancode,codesize,controversial,design,naming,unusedcode';

        $command = [
            'vendor/bin/phpmd',
            $filePath,
            'json',
            $rules,
        ];

        $process = new Process($command, $this->projectRoot, null, null, $this->timeoutSeconds);
        $process->run();

        if ($process->isSuccessful() || 2 === $process->getExitCode()) {
            $output = $process->getOutput();

            try {
                $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
                $issues = [];

                foreach ($data['files'] ?? [] as $fileData) {
                    foreach ($fileData['violations'] ?? [] as $violation) {
                        $issues[] = [
                            'file' => $fileData['file'],
                            'line' => $violation['beginLine'] ?? null,
                            'message' => $violation['description'] ?? '',
                            'severity' => $this->mapPHPMDSeverity($violation['priority'] ?? 3),
                            'rule' => $violation['rule'] ?? 'phpmd_rule',
                            'tool' => 'phpmd',
                            'ruleset' => $violation['ruleSet'] ?? null,
                        ];
                    }
                }

                return [
                    'success' => true,
                    'issues' => $issues,
                ];
            } catch (\JsonException $e) {
                throw new \RuntimeException('Failed to parse PHPMD output: '.$e->getMessage());
            }
        }

        throw new ProcessFailedException($process);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function runPHPCPD(string $filePath, array $config): array
    {
        $minLines = $config['min_lines'] ?? 5;
        $minTokens = $config['min_tokens'] ?? 70;

        $command = [
            'vendor/bin/phpcpd',
            '--log-pmd=/dev/stdout',
            '--min-lines='.$minLines,
            '--min-tokens='.$minTokens,
            $filePath,
        ];

        $process = new Process($command, $this->projectRoot, null, null, $this->timeoutSeconds);
        $process->run();

        // PHPCPD returns 1 when duplications are found
        if ($process->isSuccessful() || 1 === $process->getExitCode()) {
            $output = $process->getOutput();
            $issues = [];

            // Parse PMD XML output for duplications
            if (!empty($output) && str_contains($output, '<pmd-cpd>')) {
                try {
                    $xml = simplexml_load_string($output);

                    foreach ($xml->duplication as $duplication) {
                        $issues[] = [
                            'file' => (string) $duplication->file[0]['path'],
                            'line' => (int) $duplication->file[0]['line'],
                            'message' => \sprintf(
                                'Code duplication detected: %d lines, %d tokens',
                                (int) $duplication['lines'],
                                (int) $duplication['tokens']
                            ),
                            'severity' => 'medium',
                            'rule' => 'code_duplication',
                            'tool' => 'phpcpd',
                            'duplication_info' => [
                                'lines' => (int) $duplication['lines'],
                                'tokens' => (int) $duplication['tokens'],
                                'files' => array_map(fn ($file) => [
                                    'path' => (string) $file['path'],
                                    'line' => (int) $file['line'],
                                ], iterator_to_array($duplication->file)),
                            ],
                        ];
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to parse PHPCPD XML output', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'issues' => $issues,
            ];
        }

        throw new ProcessFailedException($process);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function mapPHPStanSeverity(array $message): string
    {
        // PHPStan doesn't have explicit severity levels, treat all as errors
        return 'high';
    }

    private function mapPsalmSeverity(string $severity): string
    {
        return match (strtolower($severity)) {
            'error' => 'high',
            'warning' => 'medium',
            'info' => 'low',
            default => 'medium',
        };
    }

    private function mapPHPMDSeverity(int $priority): string
    {
        return match ($priority) {
            1 => 'critical',
            2 => 'high',
            3 => 'medium',
            4 => 'low',
            default => 'medium',
        };
    }
}

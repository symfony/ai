<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\DevAssistantBundle\Tool;

use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\DevAssistantBundle\Analyzer\ArchitectureAnalyzer;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Description;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Component\Finder\Finder;

/**
 * AI-powered architecture analysis tool for comprehensive system design evaluation.
 *
 * This tool analyzes project structure, dependencies, design patterns, and provides
 * strategic architectural recommendations using advanced AI reasoning.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
#[AsTool(
    name: 'architecture_analyzer',
    description: 'Performs comprehensive AI-powered architectural analysis of project structure and design patterns'
)]
final readonly class ArchitectureAnalyzerTool
{
    public function __construct(
        private ArchitectureAnalyzer $architectureAnalyzer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Analyzes project architecture, design patterns, and system structure.
     *
     * @param string $projectPath The root path of the project to analyze
     * @param array<string> $includePaths Specific paths to include in analysis (optional)
     * @param array<string> $excludePaths Paths to exclude from analysis
     * @param string $depth Analysis depth level ('basic', 'standard', 'comprehensive', 'expert')
     * @param bool $includeMetrics Whether to include detailed architectural metrics
     *
     * @return array<string, mixed> Comprehensive architectural analysis results
     */
    public function __invoke(
        #[Description('Root path of the project to analyze')]
        string $projectPath,
        
        #[Description('Specific paths to include in analysis (relative to project root)')]
        array $includePaths = [],
        
        #[Description('Paths to exclude from analysis (relative to project root)')]
        array $excludePaths = ['vendor', 'node_modules', 'var', 'public/build'],
        
        #[Description('Depth of architectural analysis')]
        #[With(enum: ['basic', 'standard', 'comprehensive', 'expert'])]
        string $depth = 'standard',
        
        #[Description('Include detailed architectural metrics and complexity analysis')]
        bool $includeMetrics = true,
    ): array {
        $this->logger->info('Starting architecture analysis', [
            'project_path' => $projectPath,
            'include_paths' => $includePaths,
            'exclude_paths' => $excludePaths,
            'analysis_depth' => $depth,
        ]);

        try {
            // Validate project path
            if (!is_dir($projectPath)) {
                throw new \InvalidArgumentException("Project path does not exist: {$projectPath}");
            }

            // Discover PHP files for analysis
            $filePaths = $this->discoverProjectFiles($projectPath, $includePaths, $excludePaths);
            
            if (empty($filePaths)) {
                return [
                    'success' => false,
                    'error' => 'No PHP files found for analysis',
                    'project_path' => $projectPath,
                ];
            }

            // Perform AI-powered architectural analysis
            $startTime = microtime(true);
            $result = $this->architectureAnalyzer->analyzeArchitecture(
                filePaths: $filePaths,
                projectRoot: $projectPath,
                depth: $depth
            );

            $analysisTime = microtime(true) - $startTime;

            // Build comprehensive response
            $response = [
                'success' => true,
                'type' => $result->type->value,
                'summary' => $result->summary,
                'issues' => array_map(fn ($issue) => $issue->toArray(), $result->issues),
                'suggestions' => array_map(fn ($suggestion) => $suggestion->toArray(), $result->suggestions),
                'score' => $result->getScore(),
                'confidence' => $result->confidence,
                'overall_severity' => $result->overallSeverity->value,
                'metadata' => [
                    'project_path' => $projectPath,
                    'files_analyzed' => \count($filePaths),
                    'analyzed_at' => $result->analyzedAt->format(\DateTimeInterface::ATOM),
                    'analysis_duration_seconds' => round($analysisTime, 3),
                    'analysis_depth' => $depth,
                ],
            ];

            if ($includeMetrics) {
                $response['metrics'] = array_merge(
                    $result->metrics,
                    $this->calculateProjectMetrics($filePaths, $projectPath)
                );
            }

            $this->logger->info('Architecture analysis completed', [
                'files_analyzed' => \count($filePaths),
                'issues_found' => \count($result->issues),
                'suggestions_generated' => \count($result->suggestions),
                'analysis_time_seconds' => round($analysisTime, 3),
            ]);

            return $response;

        } catch (\Throwable $e) {
            $this->logger->error('Architecture analysis failed', [
                'error' => $e->getMessage(),
                'project_path' => $projectPath,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Architecture analysis failed: ' . $e->getMessage(),
                'project_path' => $projectPath,
                'metadata' => [
                    'error_occurred' => true,
                    'error_message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param array<string> $includePaths
     * @param array<string> $excludePaths
     * @return array<string>
     */
    private function discoverProjectFiles(string $projectPath, array $includePaths, array $excludePaths): array
    {
        $finder = new Finder();
        $finder->files()
               ->name('*.php')
               ->ignoreDotFiles(true)
               ->ignoreVCS(true);

        // Configure paths to search
        if (!empty($includePaths)) {
            foreach ($includePaths as $includePath) {
                $fullPath = $projectPath . DIRECTORY_SEPARATOR . $includePath;
                if (is_dir($fullPath)) {
                    $finder->in($fullPath);
                } elseif (is_file($fullPath)) {
                    // Individual file specified
                    return [$fullPath];
                }
            }
        } else {
            $finder->in($projectPath);
        }

        // Configure paths to exclude
        foreach ($excludePaths as $excludePath) {
            $finder->exclude($excludePath);
        }

        // Convert to array of absolute paths
        $filePaths = [];
        foreach ($finder as $file) {
            $filePaths[] = $file->getRealPath();
        }

        // Sort for consistent results
        sort($filePaths);

        return $filePaths;
    }

    /**
     * @param array<string> $filePaths
     * @return array<string, mixed>
     */
    private function calculateProjectMetrics(array $filePaths, string $projectRoot): array
    {
        $metrics = [
            'project_size' => [
                'total_files' => \count($filePaths),
                'total_lines' => 0,
                'total_size_bytes' => 0,
            ],
            'namespace_distribution' => [],
            'class_types' => [
                'classes' => 0,
                'interfaces' => 0,
                'traits' => 0,
                'enums' => 0,
            ],
            'directory_structure' => [],
        ];

        $namespaces = [];
        $directoryFiles = [];

        foreach ($filePaths as $filePath) {
            if (!is_readable($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            // Calculate file metrics
            $lines = substr_count($content, "\n") + 1;
            $bytes = \strlen($content);
            
            $metrics['project_size']['total_lines'] += $lines;
            $metrics['project_size']['total_size_bytes'] += $bytes;

            // Extract namespace
            if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                $namespace = $matches[1];
                $namespaces[$namespace] = ($namespaces[$namespace] ?? 0) + 1;
            }

            // Count class types
            $metrics['class_types']['classes'] += preg_match_all('/\bclass\s+\w+/', $content);
            $metrics['class_types']['interfaces'] += preg_match_all('/\binterface\s+\w+/', $content);
            $metrics['class_types']['traits'] += preg_match_all('/\btrait\s+\w+/', $content);
            $metrics['class_types']['enums'] += preg_match_all('/\benum\s+\w+/', $content);

            // Directory distribution
            $relativePath = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $filePath);
            $directory = \dirname($relativePath);
            $directoryFiles[$directory] = ($directoryFiles[$directory] ?? 0) + 1;
        }

        // Sort namespaces by frequency
        arsort($namespaces);
        $metrics['namespace_distribution'] = array_slice($namespaces, 0, 10, true);

        // Sort directories by file count
        arsort($directoryFiles);
        $metrics['directory_structure'] = array_slice($directoryFiles, 0, 15, true);

        // Calculate averages
        if ($metrics['project_size']['total_files'] > 0) {
            $metrics['project_size']['average_lines_per_file'] = (int) (
                $metrics['project_size']['total_lines'] / $metrics['project_size']['total_files']
            );
            $metrics['project_size']['average_size_per_file_kb'] = round(
                $metrics['project_size']['total_size_bytes'] / $metrics['project_size']['total_files'] / 1024, 
                2
            );
        }

        return $metrics;
    }
}

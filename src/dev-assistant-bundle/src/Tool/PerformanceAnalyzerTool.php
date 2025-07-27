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
use Symfony\AI\DevAssistantBundle\Analyzer\PerformanceAnalyzer;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Description;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

/**
 * AI-powered performance analysis tool with advanced optimization algorithms.
 *
 * This tool combines AI analysis with static performance metrics to identify
 * bottlenecks, optimize algorithmic complexity, and provide actionable performance improvements.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
#[AsTool(
    name: 'performance_analyzer',
    description: 'Performs AI-powered performance analysis with algorithmic complexity optimization and bottleneck detection'
)]
final readonly class PerformanceAnalyzerTool
{
    public function __construct(
        private PerformanceAnalyzer $performanceAnalyzer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Analyzes code for performance issues, algorithmic complexity, and optimization opportunities.
     *
     * @param string $code The PHP code to analyze for performance issues
     * @param string|null $filePath Optional file path for better context and error reporting
     * @param string $depth Analysis depth level ('basic', 'standard', 'comprehensive', 'expert')
     * @param bool $includeMemoryAnalysis Whether to include memory usage analysis
     * @param bool $includeDatabaseOptimization Whether to analyze database query performance
     * @param bool $includeAlgorithmicAnalysis Whether to perform Big O complexity analysis
     * @param bool $includeCachingRecommendations Whether to suggest caching opportunities
     *
     * @return array<string, mixed> Comprehensive performance analysis results with optimization recommendations
     */
    public function __invoke(
        #[Description('The PHP code to analyze for performance optimization opportunities')]
        string $code,
        
        #[Description('Optional file path for better context and performance profiling')]
        #[With(nullable: true)]
        ?string $filePath = null,
        
        #[Description('Depth of performance analysis to perform')]
        #[With(enum: ['basic', 'standard', 'comprehensive', 'expert'])]
        string $depth = 'standard',
        
        #[Description('Include memory usage patterns and garbage collection analysis')]
        bool $includeMemoryAnalysis = true,
        
        #[Description('Include database query optimization and N+1 query detection')]
        bool $includeDatabaseOptimization = true,
        
        #[Description('Include algorithmic complexity (Big O) analysis and optimization suggestions')]
        bool $includeAlgorithmicAnalysis = true,
        
        #[Description('Include caching strategy recommendations and CDN optimization')]
        bool $includeCachingRecommendations = true,
    ): array {
        $this->logger->info('Starting performance analysis', [
            'file_path' => $filePath,
            'code_length' => \strlen($code),
            'analysis_depth' => $depth,
            'include_memory' => $includeMemoryAnalysis,
            'include_database' => $includeDatabaseOptimization,
            'include_algorithmic' => $includeAlgorithmicAnalysis,
        ]);

        try {
            // Validate input
            if (empty(trim($code))) {
                throw new \InvalidArgumentException('Code content cannot be empty');
            }

            // Pre-analysis static metrics
            $preAnalysisMetrics = $this->calculatePreAnalysisMetrics($code);

            // Perform AI-powered performance analysis
            $startTime = microtime(true);
            $result = $this->performanceAnalyzer->analyzePerformance(
                code: $code,
                filePath: $filePath,
                depth: $depth,
                includeMemoryAnalysis: $includeMemoryAnalysis,
                includeDatabaseOptimization: $includeDatabaseOptimization
            );

            $analysisTime = microtime(true) - $startTime;

            // Build comprehensive response with performance insights
            $response = [
                'success' => true,
                'type' => $result->type->value,
                'summary' => $result->summary,
                'issues' => array_map(fn ($issue) => $this->enhanceIssueWithPerformanceData($issue), $result->issues),
                'suggestions' => array_map(fn ($suggestion) => $this->enhanceSuggestionWithBenchmarks($suggestion), $result->suggestions),
                'score' => $result->getScore(),
                'confidence' => $result->confidence,
                'overall_severity' => $result->overallSeverity->value,
                'performance_grade' => $this->calculatePerformanceGrade($result->getScore(), $result->issues),
                'optimization_opportunities' => $this->identifyOptimizationOpportunities($result->issues, $result->suggestions),
                'metadata' => [
                    'file_path' => $filePath,
                    'analyzed_at' => $result->analyzedAt->format(\DateTimeInterface::ATOM),
                    'analysis_duration_ms' => round($analysisTime * 1000, 2),
                    'analysis_depth' => $depth,
                    'analysis_features' => [
                        'memory_analysis' => $includeMemoryAnalysis,
                        'database_optimization' => $includeDatabaseOptimization,
                        'algorithmic_analysis' => $includeAlgorithmicAnalysis,
                        'caching_recommendations' => $includeCachingRecommendations,
                    ],
                ],
            ];

            // Enhanced metrics combining AI and static analysis
            $response['metrics'] = array_merge(
                $preAnalysisMetrics,
                $result->metrics,
                $this->calculateAdvancedPerformanceMetrics($code, $result)
            );

            // Add algorithmic analysis if requested
            if ($includeAlgorithmicAnalysis) {
                $response['algorithmic_analysis'] = $this->performAlgorithmicComplexityAnalysis($code);
            }

            // Add caching recommendations if requested
            if ($includeCachingRecommendations) {
                $response['caching_recommendations'] = $this->generateCachingRecommendations($code, $result);
            }

            $this->logger->info('Performance analysis completed', [
                'performance_issues' => \count($result->issues),
                'optimization_suggestions' => \count($result->suggestions),
                'performance_grade' => $response['performance_grade'],
                'analysis_time_ms' => $response['metadata']['analysis_duration_ms'],
            ]);

            return $response;

        } catch (\Throwable $e) {
            $this->logger->error('Performance analysis failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Performance analysis failed: ' . $e->getMessage(),
                'type' => 'performance',
                'issues' => [],
                'suggestions' => [],
                'score' => 0.0,
                'performance_grade' => 'F',
                'metadata' => [
                    'error_occurred' => true,
                    'error_message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function calculatePreAnalysisMetrics(string $code): array
    {
        $lines = explode("\n", $code);
        $nonEmptyLines = array_filter($lines, fn ($line) => !empty(trim($line)));
        
        return [
            'pre_analysis' => [
                'total_lines' => \count($lines),
                'code_lines' => \count($nonEmptyLines),
                'function_count' => substr_count($code, 'function '),
                'class_count' => substr_count($code, 'class '),
                'loop_count' => substr_count($code, 'for') + substr_count($code, 'while') + substr_count($code, 'foreach'),
                'conditional_count' => substr_count($code, 'if ') + substr_count($code, 'switch'),
                'estimated_complexity' => $this->estimateComplexityScore($code),
            ],
        ];
    }

    private function estimateComplexityScore(string $code): float
    {
        $complexityFactors = [
            'nested_loops' => substr_count($code, 'foreach') * substr_count($code, 'for') * 2,
            'recursive_calls' => preg_match_all('/function\s+\w+[^{]*{[^}]*\w+\([^)]*\)/', $code),
            'database_calls' => substr_count($code, '->find') + substr_count($code, '->query'),
            'file_operations' => substr_count($code, 'file_') + substr_count($code, 'fopen'),
        ];

        return array_sum($complexityFactors) / 10; // Normalize to 0-10 scale
    }

    /**
     * @param \Symfony\AI\DevAssistantBundle\Model\Issue $issue
     * @return array<string, mixed>
     */
    private function enhanceIssueWithPerformanceData($issue): array
    {
        $issueArray = $issue->toArray();
        
        // Add performance-specific enhancements
        if ($issue->category->value === 'performance') {
            $issueArray['performance_impact'] = $this->estimatePerformanceImpact($issue);
            $issueArray['optimization_priority'] = $this->calculateOptimizationPriority($issue);
        }

        return $issueArray;
    }

    /**
     * @param \Symfony\AI\DevAssistantBundle\Model\Suggestion $suggestion
     * @return array<string, mixed>
     */
    private function enhanceSuggestionWithBenchmarks($suggestion): array
    {
        $suggestionArray = $suggestion->toArray();
        
        // Add benchmarking guidance for performance suggestions
        if ($suggestion->type->value === 'optimization') {
            $suggestionArray['benchmark_strategy'] = $this->generateBenchmarkStrategy($suggestion);
            $suggestionArray['expected_improvement'] = $this->estimateExpectedImprovement($suggestion);
        }

        return $suggestionArray;
    }

    /**
     * @param array<\Symfony\AI\DevAssistantBundle\Model\Issue> $issues
     * @param array<\Symfony\AI\DevAssistantBundle\Model\Suggestion> $suggestions
     * @return array<string, mixed>
     */
    private function identifyOptimizationOpportunities(array $issues, array $suggestions): array
    {
        $opportunities = [
            'quick_wins' => [],
            'high_impact' => [],
            'long_term' => [],
        ];

        foreach ($suggestions as $suggestion) {
            $impact = $suggestion->estimatedImpact ?? 0.5;
            $implementation = $suggestion->implementation ?? '';
            
            if ($impact > 0.8 && str_contains(strtolower($implementation), 'simple')) {
                $opportunities['quick_wins'][] = $suggestion->title;
            } elseif ($impact > 0.7) {
                $opportunities['high_impact'][] = $suggestion->title;
            } else {
                $opportunities['long_term'][] = $suggestion->title;
            }
        }

        return $opportunities;
    }

    /**
     * @param \Symfony\AI\DevAssistantBundle\Model\AnalysisResult $result
     * @return array<string, mixed>
     */
    private function calculateAdvancedPerformanceMetrics(string $code, $result): array
    {
        return [
            'advanced_metrics' => [
                'bottleneck_score' => $this->calculateBottleneckScore($result->issues),
                'scalability_index' => $this->calculateScalabilityIndex($code),
                'memory_efficiency' => $this->calculateMemoryEfficiency($code),
                'cache_potential' => $this->calculateCachePotential($code),
                'optimization_roi' => $this->calculateOptimizationROI($result->suggestions),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function performAlgorithmicComplexityAnalysis(string $code): array
    {
        return [
            'estimated_big_o' => $this->estimateBigOComplexity($code),
            'complexity_hotspots' => $this->identifyComplexityHotspots($code),
            'optimization_potential' => $this->calculateOptimizationPotential($code),
            'recommended_algorithms' => $this->recommendAlgorithms($code),
        ];
    }

    /**
     * @param \Symfony\AI\DevAssistantBundle\Model\AnalysisResult $result
     * @return array<string, mixed>
     */
    private function generateCachingRecommendations(string $code, $result): array
    {
        return [
            'cache_opportunities' => $this->identifyCacheOpportunities($code),
            'recommended_strategies' => $this->recommendCachingStrategies($code),
            'cache_invalidation' => $this->suggestCacheInvalidation($code),
            'performance_gain_estimate' => $this->estimateCachingGains($code),
        ];
    }

    private function calculatePerformanceGrade(float $score, array $issues): string
    {
        $criticalIssues = array_filter($issues, fn ($issue) => $issue->severity->value === 'critical');
        $highIssues = array_filter($issues, fn ($issue) => $issue->severity->value === 'high');

        if (!empty($criticalIssues) || $score < 20) {
            return 'F';
        } elseif (!empty($highIssues) || $score < 40) {
            return 'D';
        } elseif ($score < 60) {
            return 'C';
        } elseif ($score < 80) {
            return 'B';
        } else {
            return 'A';
        }
    }

    // Helper methods for performance calculations
    private function estimatePerformanceImpact($issue): string
    {
        return match ($issue->severity->value) {
            'critical' => 'High impact - may cause significant slowdown',
            'high' => 'Moderate impact - noticeable performance degradation',
            'medium' => 'Low impact - minor performance effects',
            default => 'Minimal impact - optimization opportunity',
        };
    }

    private function calculateOptimizationPriority($issue): string
    {
        return match ($issue->severity->value) {
            'critical' => 'Urgent - fix immediately',
            'high' => 'High - address in current sprint',
            'medium' => 'Medium - schedule for next iteration',
            default => 'Low - optimize when convenient',
        };
    }

    private function generateBenchmarkStrategy($suggestion): string
    {
        return "Use performance profiling tools like Blackfire or XDebug to measure before/after metrics. " .
               "Focus on response time, memory usage, and CPU utilization.";
    }

    private function estimateExpectedImprovement($suggestion): string
    {
        $impact = $suggestion->estimatedImpact ?? 0.5;
        
        if ($impact > 0.8) {
            return "Significant improvement expected (20-50% performance gain)";
        } elseif ($impact > 0.6) {
            return "Moderate improvement expected (10-20% performance gain)";
        } else {
            return "Minor improvement expected (5-10% performance gain)";
        }
    }

    private function calculateBottleneckScore(array $issues): float
    {
        $weights = ['critical' => 3, 'high' => 2, 'medium' => 1, 'low' => 0.5];
        $score = 0;
        
        foreach ($issues as $issue) {
            $score += $weights[$issue->severity->value] ?? 0;
        }
        
        return min($score / 10, 10); // Normalize to 0-10
    }

    private function calculateScalabilityIndex(string $code): float
    {
        $factors = [
            'nested_loops' => substr_count($code, 'foreach') * substr_count($code, 'for'),
            'n_plus_one' => preg_match_all('/foreach[^{]*{[^}]*->find\(/', $code),
            'memory_operations' => substr_count($code, 'array_merge') + substr_count($code, 'array_map'),
        ];
        
        return max(0, 10 - array_sum($factors)); // Higher is better
    }

    private function calculateMemoryEfficiency(string $code): float
    {
        $inefficiencies = substr_count($code, 'array_merge') + 
                         substr_count($code, 'str_repeat') + 
                         substr_count($code, 'range(');
        
        return max(0, 10 - $inefficiencies * 0.5);
    }

    private function calculateCachePotential(string $code): float
    {
        $cacheable = substr_count($code, '->find') + 
                    substr_count($code, 'file_get_contents') + 
                    substr_count($code, 'curl_exec');
        
        return min($cacheable * 2, 10);
    }

    private function calculateOptimizationROI(array $suggestions): float
    {
        if (empty($suggestions)) {
            return 0;
        }
        
        $totalImpact = array_reduce($suggestions, fn ($sum, $s) => $sum + ($s->estimatedImpact ?? 0), 0);
        return ($totalImpact / \count($suggestions)) * 10;
    }

    private function estimateBigOComplexity(string $code): string
    {
        $nestedLoops = preg_match_all('/for[^{]*{[^{}]*for[^{]*{/', $code);
        $singleLoops = substr_count($code, 'foreach') + substr_count($code, 'for') - $nestedLoops * 2;
        
        if ($nestedLoops > 0) {
            return 'O(n²) - Quadratic complexity detected';
        } elseif ($singleLoops > 0) {
            return 'O(n) - Linear complexity';
        } else {
            return 'O(1) - Constant time complexity';
        }
    }

    private function identifyComplexityHotspots(string $code): array
    {
        $hotspots = [];
        
        if (preg_match_all('/for[^{]*{[^{}]*for[^{]*{/', $code)) {
            $hotspots[] = 'Nested loops detected - consider optimization';
        }
        
        if (preg_match_all('/while[^{]*{[^{}]*while[^{]*{/', $code)) {
            $hotspots[] = 'Nested while loops - potential infinite loop risk';
        }
        
        return $hotspots;
    }

    private function calculateOptimizationPotential(string $code): float
    {
        $optimizable = substr_count($code, 'array_search') + 
                      substr_count($code, 'in_array') + 
                      preg_match_all('/for.*count\(/', $code);
        
        return min($optimizable * 1.5, 10);
    }

    private function recommendAlgorithms(string $code): array
    {
        $recommendations = [];
        
        if (str_contains($code, 'array_search')) {
            $recommendations[] = 'Consider using hash tables (array with keys) for O(1) lookups';
        }
        
        if (str_contains($code, 'usort') || str_contains($code, 'sort')) {
            $recommendations[] = 'Consider pre-sorting data or using SplPriorityQueue for better performance';
        }
        
        return $recommendations;
    }

    private function identifyCacheOpportunities(string $code): array
    {
        $opportunities = [];
        
        if (str_contains($code, '->find') || str_contains($code, '->findBy')) {
            $opportunities[] = 'Database query results';
        }
        
        if (str_contains($code, 'file_get_contents') || str_contains($code, 'curl_exec')) {
            $opportunities[] = 'External API calls and file operations';
        }
        
        return $opportunities;
    }

    private function recommendCachingStrategies(string $code): array
    {
        return [
            'Redis/Memcached for frequently accessed data',
            'Symfony Cache component for flexible caching',
            'HTTP caching for API responses',
            'Database query result caching',
        ];
    }

    private function suggestCacheInvalidation(string $code): array
    {
        return [
            'Time-based expiration for static data',
            'Tag-based invalidation for related data',
            'Event-driven cache clearing on data updates',
        ];
    }

    private function estimateCachingGains(string $code): string
    {
        $cacheableOperations = substr_count($code, '->find') + substr_count($code, 'file_get_contents');
        
        if ($cacheableOperations > 5) {
            return "High potential - 30-70% performance improvement";
        } elseif ($cacheableOperations > 2) {
            return "Moderate potential - 15-30% performance improvement";
        } else {
            return "Low potential - 5-15% performance improvement";
        }
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\DevAssistantBundle\Analyzer;

use Psr\Log\LoggerInterface;
use Symfony\AI\DevAssistantBundle\Model\AnalysisResult;
use Symfony\AI\DevAssistantBundle\Model\AnalysisType;
use Symfony\AI\DevAssistantBundle\Model\Issue;
use Symfony\AI\DevAssistantBundle\Model\IssueCategory;
use Symfony\AI\DevAssistantBundle\Model\Severity;
use Symfony\AI\DevAssistantBundle\Model\Suggestion;
use Symfony\AI\DevAssistantBundle\Model\SuggestionType;
use Symfony\AI\DevAssistantBundle\Model\Priority;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * AI-powered performance analyzer using advanced algorithms and pattern recognition.
 *
 * Leverages machine learning models to identify performance bottlenecks, memory issues,
 * database optimization opportunities, and scalability concerns through code analysis.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class PerformanceAnalyzer
{
    public function __construct(
        private PlatformInterface $platform,
        private LoggerInterface $logger,
    ) {
    }

    public function analyzePerformance(
        string $code,
        ?string $filePath = null,
        string $depth = 'standard',
        bool $includeMemoryAnalysis = true,
        bool $includeDatabaseOptimization = true
    ): AnalysisResult {
        $this->logger->info('Starting AI performance analysis', [
            'file_path' => $filePath,
            'code_length' => \strlen($code),
            'include_memory' => $includeMemoryAnalysis,
            'include_database' => $includeDatabaseOptimization,
        ]);

        $startTime = microtime(true);

        // Perform static performance analysis first
        $staticMetrics = $this->analyzeStaticPerformanceMetrics($code);

        // Build sophisticated performance analysis prompt
        $prompt = $this->buildPerformancePrompt(
            $code,
            $filePath,
            $depth,
            $staticMetrics,
            $includeMemoryAnalysis,
            $includeDatabaseOptimization
        );

        $messages = new MessageBag(
            Message::forSystem($this->getPerformanceSystemPrompt($depth)),
            Message::ofUser($prompt)
        );

        $result = $this->platform->invoke(
            model: 'claude-3-5-sonnet-20241022', // Best for performance analysis
            messages: $messages,
            options: [
                'temperature' => 0.1, // Very low for precise technical analysis
                'max_tokens' => 3500,
            ]
        )->getResult();

        if (!$result instanceof TextResult) {
            throw new \RuntimeException('Expected TextResult from performance analysis');
        }

        $analysisData = $this->parsePerformanceResponse($result->getContent());
        
        // Enhance with static metrics
        $analysisData['metrics'] = array_merge($staticMetrics, $analysisData['metrics']);
        
        $analysisTime = microtime(true) - $startTime;

        $this->logger->info('AI performance analysis completed', [
            'analysis_time_seconds' => round($analysisTime, 3),
            'performance_issues' => \count($analysisData['issues']),
            'optimization_suggestions' => \count($analysisData['suggestions']),
        ]);

        return new AnalysisResult(
            type: AnalysisType::PERFORMANCE,
            summary: $analysisData['summary'],
            issues: $analysisData['issues'],
            suggestions: $analysisData['suggestions'],
            metrics: $analysisData['metrics'],
            overallSeverity: $this->calculateOverallSeverity($analysisData['issues']),
            confidence: $analysisData['confidence'],
            analyzedAt: new \DateTimeImmutable(),
        );
    }

    private function getPerformanceSystemPrompt(string $depth): string
    {
        return <<<'PROMPT'
You are a senior performance engineer and optimization specialist with 15+ years of experience in:

- Application performance monitoring and tuning
- Database optimization and query analysis
- Memory management and garbage collection optimization
- Caching strategies and CDN optimization
- Load testing and scalability analysis
- Algorithmic complexity analysis and Big O optimization
- PHP-specific performance patterns and anti-patterns
- Symfony framework performance best practices

Analyze the provided PHP code for performance issues and return findings in this JSON format:

{
  "summary": "Executive summary of performance analysis and critical findings",
  "issues": [
    {
      "id": "perf_issue_id",
      "title": "Performance issue title",
      "description": "Detailed analysis of the performance problem",
      "severity": "critical|high|medium|low|info",
      "category": "performance",
      "rule": "performance principle violated",
      "reasoning": "Performance impact analysis with metrics",
      "fixSuggestion": "Specific optimization technique",
      "performanceImpact": "quantified impact (e.g., +200ms response time)",
      "complexity": "O(n) -> O(log n) improvement potential"
    }
  ],
  "suggestions": [
    {
      "id": "perf_suggestion_id",
      "title": "Performance optimization recommendation",
      "description": "Detailed optimization strategy",
      "type": "optimization",
      "priority": "urgent|high|medium|low",
      "implementation": "Step-by-step optimization guide",
      "reasoning": "Performance benefits and trade-offs",
      "benefits": ["latency_reduction", "memory_efficiency", "throughput_increase"],
      "estimatedImpact": 0.85,
      "benchmarkGuidance": "How to measure improvement"
    }
  ],
  "metrics": {
    "algorithmic_complexity": "O(n^2)",
    "memory_efficiency_score": 7.2,
    "database_efficiency_score": 6.8,
    "caching_opportunity_score": 8.5,
    "estimated_response_time_ms": 250,
    "cpu_intensity_score": 6.0,
    "scalability_score": 7.5
  },
  "confidence": 0.9
}

Focus on: algorithmic complexity, memory usage patterns, database query efficiency, caching opportunities, and Symfony-specific optimizations.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeStaticPerformanceMetrics(string $code): array
    {
        $metrics = [
            'code_complexity' => $this->calculateCyclomaticComplexity($code),
            'nested_loops_count' => $this->countNestedLoops($code),
            'database_queries_count' => $this->countDatabaseQueries($code),
            'file_operations_count' => $this->countFileOperations($code),
            'regex_operations_count' => $this->countRegexOperations($code),
            'potential_n_plus_one' => $this->detectNPlusOnePatterns($code),
            'memory_intensive_operations' => $this->detectMemoryIntensiveOperations($code),
        ];

        return $metrics;
    }

    private function calculateCyclomaticComplexity(string $code): int
    {
        // Count decision points that increase complexity
        $complexityKeywords = ['if', 'else', 'elseif', 'while', 'for', 'foreach', 'case', 'catch', '&&', '||', '?'];
        $complexity = 1; // Base complexity

        foreach ($complexityKeywords as $keyword) {
            $complexity += substr_count(strtolower($code), $keyword);
        }

        return $complexity;
    }

    private function countNestedLoops(string $code): int
    {
        $lines = explode("\n", $code);
        $loopDepth = 0;
        $maxDepth = 0;
        $nestedCount = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Detect loop starts
            if (preg_match('/\b(for|foreach|while)\s*\(/', $trimmed)) {
                $loopDepth++;
                if ($loopDepth > 1) {
                    $nestedCount++;
                }
                $maxDepth = max($maxDepth, $loopDepth);
            }
            
            // Detect loop ends (simple heuristic)
            if (preg_match('/^\s*}\s*$/', $trimmed) && $loopDepth > 0) {
                $loopDepth--;
            }
        }

        return $nestedCount;
    }

    private function countDatabaseQueries(string $code): int
    {
        $queryPatterns = [
            '/\$.*->find\(/',
            '/\$.*->findBy\(/',
            '/\$.*->createQuery\(/',
            '/\$.*->query\(/',
            '/EntityManager.*->/',
            '/Repository.*->/',
        ];

        $count = 0;
        foreach ($queryPatterns as $pattern) {
            $count += preg_match_all($pattern, $code);
        }

        return $count;
    }

    private function countFileOperations(string $code): int
    {
        $filePatterns = [
            '/file_get_contents\(/',
            '/file_put_contents\(/',
            '/fopen\(/',
            '/fread\(/',
            '/fwrite\(/',
            '/glob\(/',
        ];

        $count = 0;
        foreach ($filePatterns as $pattern) {
            $count += preg_match_all($pattern, $code);
        }

        return $count;
    }

    private function countRegexOperations(string $code): int
    {
        return preg_match_all('/preg_\w+\(/', $code);
    }

    private function detectNPlusOnePatterns(string $code): bool
    {
        // Look for patterns indicating potential N+1 queries
        $patterns = [
            '/foreach\s*\([^)]+\)\s*{[^}]*\$.*->find\(/s',
            '/foreach\s*\([^)]+\)\s*{[^}]*\$.*->findBy\(/s',
            '/for\s*\([^)]+\)\s*{[^}]*\$.*->find\(/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                return true;
            }
        }

        return false;
    }

    private function detectMemoryIntensiveOperations(string $code): int
    {
        $patterns = [
            '/array_map\(/',
            '/array_filter\(/',
            '/explode\(/',
            '/str_split\(/',
            '/range\(/',
            '/array_merge\(/',
        ];

        $count = 0;
        foreach ($patterns as $pattern) {
            $count += preg_match_all($pattern, $code);
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $staticMetrics
     */
    private function buildPerformancePrompt(
        string $code,
        ?string $filePath,
        string $depth,
        array $staticMetrics,
        bool $includeMemoryAnalysis,
        bool $includeDatabaseOptimization
    ): string {
        $prompt = "Please analyze this PHP code for performance optimization opportunities:\n\n";
        
        if ($filePath) {
            $prompt .= "File: {$filePath}\n\n";
        }

        $prompt .= "```php\n{$code}\n```\n\n";

        $prompt .= "## Static Analysis Results\n";
        $prompt .= sprintf("- Cyclomatic Complexity: %d\n", $staticMetrics['code_complexity']);
        $prompt .= sprintf("- Nested Loops: %d\n", $staticMetrics['nested_loops_count']);
        $prompt .= sprintf("- Database Query Operations: %d\n", $staticMetrics['database_queries_count']);
        $prompt .= sprintf("- File Operations: %d\n", $staticMetrics['file_operations_count']);
        $prompt .= sprintf("- Regex Operations: %d\n", $staticMetrics['regex_operations_count']);
        $prompt .= sprintf("- Potential N+1 Queries: %s\n", $staticMetrics['potential_n_plus_one'] ? 'Yes' : 'No');
        $prompt .= sprintf("- Memory Intensive Operations: %d\n", $staticMetrics['memory_intensive_operations']);

        $prompt .= "\n## Analysis Focus Areas\n";
        $prompt .= "- Algorithmic complexity and Big O optimization\n";
        $prompt .= "- Loop optimization and iteration efficiency\n";
        
        if ($includeDatabaseOptimization) {
            $prompt .= "- Database query optimization and N+1 prevention\n";
            $prompt .= "- ORM performance patterns\n";
        }
        
        if ($includeMemoryAnalysis) {
            $prompt .= "- Memory usage optimization\n";
            $prompt .= "- Garbage collection efficiency\n";
        }
        
        $prompt .= "- Caching opportunities\n";
        $prompt .= "- Symfony-specific performance optimizations\n";

        $prompt .= "\n## Analysis Depth\n";
        $prompt .= match ($depth) {
            'expert' => "Perform EXPERT performance analysis with advanced optimization techniques, profiling guidance, and enterprise-scale considerations.",
            'comprehensive' => "Perform COMPREHENSIVE performance review covering all optimization aspects with detailed benchmarking strategies.",
            'basic' => "Perform BASIC performance assessment focusing on obvious bottlenecks and quick wins.",
            default => "Perform STANDARD performance analysis with practical optimization recommendations.",
        };

        return $prompt;
    }

    /**
     * @return array{summary: string, issues: array<Issue>, suggestions: array<Suggestion>, metrics: array<string, mixed>, confidence: float}
     */
    private function parsePerformanceResponse(string $response): array
    {
        $jsonMatch = [];
        if (preg_match('/\{.*\}/s', $response, $jsonMatch)) {
            $jsonContent = $jsonMatch[0];
        } else {
            $jsonContent = $response;
        }

        try {
            $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Failed to parse performance AI response', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);

            return [
                'summary' => 'Performance analysis completed but response parsing failed',
                'issues' => [],
                'suggestions' => [],
                'metrics' => ['parse_error' => true],
                'confidence' => 0.0,
            ];
        }

        return [
            'summary' => $data['summary'] ?? 'No performance summary provided',
            'issues' => $this->parsePerformanceIssues($data['issues'] ?? []),
            'suggestions' => $this->parsePerformanceSuggestions($data['suggestions'] ?? []),
            'metrics' => $data['metrics'] ?? [],
            'confidence' => (float) ($data['confidence'] ?? 0.8),
        ];
    }

    /**
     * @param array<array<string, mixed>> $issuesData
     * @return array<Issue>
     */
    private function parsePerformanceIssues(array $issuesData): array
    {
        $issues = [];
        
        foreach ($issuesData as $issueData) {
            try {
                $issues[] = new Issue(
                    id: $issueData['id'] ?? uniqid('perf_issue_'),
                    title: $issueData['title'] ?? 'Performance issue',
                    description: $issueData['description'] ?? '',
                    severity: Severity::from($issueData['severity'] ?? 'medium'),
                    category: IssueCategory::PERFORMANCE,
                    file: $issueData['file'] ?? null,
                    line: $issueData['line'] ?? null,
                    column: $issueData['column'] ?? null,
                    rule: $issueData['rule'] ?? 'performance_optimization',
                    fixSuggestion: $issueData['fixSuggestion'] ?? null,
                    codeSnippet: $issueData['codeSnippet'] ?? null,
                    metadata: [
                        'reasoning' => $issueData['reasoning'] ?? null,
                        'performance_impact' => $issueData['performanceImpact'] ?? null,
                        'complexity' => $issueData['complexity'] ?? null,
                        'ai_generated' => true,
                        'analysis_type' => 'performance',
                    ],
                );
            } catch (\ValueError $e) {
                $this->logger->warning('Invalid performance issue data', [
                    'issue_data' => $issueData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $issues;
    }

    /**
     * @param array<array<string, mixed>> $suggestionsData
     * @return array<Suggestion>
     */
    private function parsePerformanceSuggestions(array $suggestionsData): array
    {
        $suggestions = [];
        
        foreach ($suggestionsData as $suggestionData) {
            try {
                $suggestions[] = new Suggestion(
                    id: $suggestionData['id'] ?? uniqid('perf_suggestion_'),
                    title: $suggestionData['title'] ?? 'Performance optimization',
                    description: $suggestionData['description'] ?? '',
                    type: SuggestionType::OPTIMIZATION,
                    priority: Priority::from($suggestionData['priority'] ?? 'medium'),
                    implementation: $suggestionData['implementation'] ?? null,
                    reasoning: $suggestionData['reasoning'] ?? null,
                    exampleCode: $suggestionData['exampleCode'] ?? null,
                    benefits: $suggestionData['benefits'] ?? [],
                    estimatedImpact: $suggestionData['estimatedImpact'] ?? null,
                    metadata: [
                        'benchmark_guidance' => $suggestionData['benchmarkGuidance'] ?? null,
                        'ai_generated' => true,
                        'analysis_type' => 'performance',
                    ],
                );
            } catch (\ValueError $e) {
                $this->logger->warning('Invalid performance suggestion data', [
                    'suggestion_data' => $suggestionData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $suggestions;
    }

    /**
     * @param array<Issue> $issues
     */
    private function calculateOverallSeverity(array $issues): Severity
    {
        if (empty($issues)) {
            return Severity::INFO;
        }

        $maxSeverity = Severity::INFO;
        foreach ($issues as $issue) {
            if ($issue->severity->getPriority() > $maxSeverity->getPriority()) {
                $maxSeverity = $issue->severity;
            }
        }

        return $maxSeverity;
    }
}

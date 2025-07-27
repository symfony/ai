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
use Symfony\AI\DevAssistantBundle\Analyzer\CodeQualityAnalyzer;
use Symfony\AI\DevAssistantBundle\Model\AnalysisResult;
use Symfony\AI\DevAssistantBundle\Model\AnalysisType;
use Symfony\AI\DevAssistantBundle\Provider\StaticAnalysisProvider;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Description;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

/**
 * AI-powered code review tool that analyzes PHP code for quality, style, and best practices.
 *
 * This tool combines AI analysis with static analysis tools to provide comprehensive
 * code reviews that follow Symfony coding standards and enterprise best practices.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
#[AsTool(
    name: 'code_review',
    description: 'Performs comprehensive AI-powered code quality review with static analysis integration'
)]
final readonly class CodeReviewTool
{
    public function __construct(
        private CodeQualityAnalyzer $codeAnalyzer,
        private StaticAnalysisProvider $staticAnalysisProvider,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Analyzes PHP code for quality, style, maintainability, and adherence to best practices.
     *
     * @param string $code The PHP code to analyze (can be a single file or multiple files)
     * @param string|null $filePath Optional file path for context and better error reporting
     * @param array<string> $rules Specific rules to apply ('psr12', 'solid', 'symfony_standards', 'phpstan_level_8')
     * @param string $depth Analysis depth level ('basic', 'standard', 'comprehensive', 'expert')
     * @param bool $includeStaticAnalysis Whether to include results from static analysis tools
     * @param bool $includeSuggestions Whether to include improvement suggestions
     *
     * @return array<string, mixed> Comprehensive analysis results with issues, suggestions, and metrics
     */
    public function __invoke(
        #[Description('The PHP code to analyze for quality and best practices')]
        string $code,
        
        #[Description('Optional file path for better context and error reporting')]
        #[With(nullable: true)]
        ?string $filePath = null,
        
        #[Description('Specific quality rules to apply during analysis')]
        #[With(items: ['type' => 'string', 'enum' => ['psr12', 'solid', 'symfony_standards', 'phpstan_level_8', 'design_patterns']])]
        array $rules = ['psr12', 'solid', 'symfony_standards'],
        
        #[Description('Depth of analysis to perform')]
        #[With(enum: ['basic', 'standard', 'comprehensive', 'expert'])]
        string $depth = 'standard',
        
        #[Description('Include static analysis tools results (PHPStan, Psalm, PHP-CS-Fixer)')]
        bool $includeStaticAnalysis = true,
        
        #[Description('Include AI-generated improvement suggestions')]
        bool $includeSuggestions = true,
    ): array {
        $this->logger->info('Starting code review analysis', [
            'file_path' => $filePath,
            'code_length' => \strlen($code),
            'rules' => $rules,
            'depth' => $depth,
            'include_static_analysis' => $includeStaticAnalysis,
        ]);

        try {
            // Perform AI-powered code analysis
            $aiAnalysis = $this->codeAnalyzer->analyze(
                code: $code,
                filePath: $filePath,
                rules: $rules,
                depth: $depth,
                includeSuggestions: $includeSuggestions
            );

            // Integrate static analysis if requested
            $staticAnalysisResults = [];
            if ($includeStaticAnalysis && null !== $filePath) {
                $staticAnalysisResults = $this->staticAnalysisProvider->analyze($filePath);
            }

            // Combine results and create comprehensive report
            $result = $this->buildComprehensiveReport(
                $aiAnalysis,
                $staticAnalysisResults,
                $code,
                $filePath
            );

            $this->logger->info('Code review analysis completed', [
                'issues_found' => \count($result['issues']),
                'suggestions_count' => \count($result['suggestions']),
                'overall_score' => $result['score'],
                'analysis_duration' => $result['metadata']['analysis_duration_ms'],
            ]);

            return $result;

        } catch (\Throwable $e) {
            $this->logger->error('Code review analysis failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Analysis failed: ' . $e->getMessage(),
                'type' => AnalysisType::CODE_QUALITY->value,
                'issues' => [],
                'suggestions' => [],
                'score' => 0.0,
                'metadata' => [
                    'error_occurred' => true,
                    'error_message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param array<string, mixed> $staticAnalysisResults
     */
    private function buildComprehensiveReport(
        AnalysisResult $aiAnalysis,
        array $staticAnalysisResults,
        string $code,
        ?string $filePath
    ): array {
        $startTime = microtime(true);

        // Convert issues and suggestions to arrays for JSON response
        $issues = array_map(fn ($issue) => $issue->toArray(), $aiAnalysis->issues);
        $suggestions = array_map(fn ($suggestion) => $suggestion->toArray(), $aiAnalysis->suggestions);

        // Merge static analysis issues if available
        if (!empty($staticAnalysisResults)) {
            $issues = array_merge($issues, $this->convertStaticAnalysisIssues($staticAnalysisResults));
        }

        // Calculate comprehensive metrics
        $metrics = $this->calculateMetrics($code, $issues, $suggestions);

        $analysisDuration = (microtime(true) - $startTime) * 1000;

        return [
            'success' => true,
            'type' => $aiAnalysis->type->value,
            'summary' => $aiAnalysis->summary,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'score' => $aiAnalysis->getScore(),
            'confidence' => $aiAnalysis->confidence,
            'overall_severity' => $aiAnalysis->overallSeverity->value,
            'metrics' => array_merge($aiAnalysis->metrics, $metrics),
            'metadata' => [
                'file_path' => $filePath,
                'analyzed_at' => $aiAnalysis->analyzedAt->format(\DateTimeInterface::ATOM),
                'analysis_duration_ms' => round($analysisDuration, 2),
                'static_analysis_included' => !empty($staticAnalysisResults),
                'code_stats' => $this->getCodeStatistics($code),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $staticResults
     * @return array<array<string, mixed>>
     */
    private function convertStaticAnalysisIssues(array $staticResults): array
    {
        $issues = [];
        
        foreach ($staticResults as $tool => $results) {
            foreach ($results['issues'] ?? [] as $issue) {
                $issues[] = [
                    'id' => 'static_' . uniqid(),
                    'title' => $issue['message'] ?? 'Static analysis issue',
                    'description' => $issue['description'] ?? $issue['message'] ?? '',
                    'severity' => $this->mapStaticAnalysisSeverity($issue['severity'] ?? 'medium'),
                    'category' => 'code_style',
                    'file' => $issue['file'] ?? null,
                    'line' => $issue['line'] ?? null,
                    'column' => $issue['column'] ?? null,
                    'rule' => $issue['rule'] ?? $tool,
                    'fixSuggestion' => $issue['fix'] ?? null,
                    'codeSnippet' => $issue['snippet'] ?? null,
                    'metadata' => [
                        'source' => $tool,
                        'static_analysis' => true,
                    ],
                ];
            }
        }

        return $issues;
    }

    private function mapStaticAnalysisSeverity(string $severity): string
    {
        return match (strtolower($severity)) {
            'error', 'fatal' => 'critical',
            'warning' => 'high',
            'notice', 'info' => 'medium',
            default => 'low',
        };
    }

    /**
     * @param array<array<string, mixed>> $issues
     * @param array<array<string, mixed>> $suggestions
     * @return array<string, mixed>
     */
    private function calculateMetrics(string $code, array $issues, array $suggestions): array
    {
        $lines = explode("\n", $code);
        $nonEmptyLines = array_filter($lines, fn ($line) => !empty(trim($line)));
        
        return [
            'total_issues' => \count($issues),
            'critical_issues' => \count(array_filter($issues, fn ($i) => $i['severity'] === 'critical')),
            'high_issues' => \count(array_filter($issues, fn ($i) => $i['severity'] === 'high')),
            'medium_issues' => \count(array_filter($issues, fn ($i) => $i['severity'] === 'medium')),
            'low_issues' => \count(array_filter($issues, fn ($i) => $i['severity'] === 'low')),
            'suggestions_count' => \count($suggestions),
            'fixable_issues' => \count(array_filter($issues, fn ($i) => $i['fixable'])),
            'issues_per_line' => \count($nonEmptyLines) > 0 ? round(\count($issues) / \count($nonEmptyLines), 4) : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCodeStatistics(string $code): array
    {
        $lines = explode("\n", $code);
        $nonEmptyLines = array_filter($lines, fn ($line) => !empty(trim($line)));
        $commentLines = array_filter($lines, fn ($line) => preg_match('/^\s*(\/\/|\/\*|\*|#)/', $line));

        return [
            'total_lines' => \count($lines),
            'code_lines' => \count($nonEmptyLines),
            'comment_lines' => \count($commentLines),
            'blank_lines' => \count($lines) - \count($nonEmptyLines),
            'characters' => \strlen($code),
            'comment_ratio' => \count($nonEmptyLines) > 0 ? round(\count($commentLines) / \count($nonEmptyLines), 2) : 0,
        ];
    }
}

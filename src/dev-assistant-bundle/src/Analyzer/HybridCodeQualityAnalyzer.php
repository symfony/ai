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
use Symfony\AI\DevAssistantBundle\Contract\AnalyzerInterface;
use Symfony\AI\DevAssistantBundle\Model\AnalysisRequest;
use Symfony\AI\DevAssistantBundle\Model\AnalysisResult;
use Symfony\AI\DevAssistantBundle\Model\AnalysisType;
use Symfony\AI\DevAssistantBundle\Model\Issue;
use Symfony\AI\DevAssistantBundle\Model\IssueCategory;
use Symfony\AI\DevAssistantBundle\Model\Priority;
use Symfony\AI\DevAssistantBundle\Model\Severity;
use Symfony\AI\DevAssistantBundle\Model\Suggestion;
use Symfony\AI\DevAssistantBundle\Model\SuggestionType;
use Symfony\AI\ToolBox\ToolboxRunner;

/**
 * Hybrid code quality analyzer that uses AI when available, falls back to static analysis.
 *
 * This analyzer demonstrates production-ready AI integration with graceful degradation
 * when AI providers are unavailable or misconfigured.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class HybridCodeQualityAnalyzer implements AnalyzerInterface
{
    public function __construct(
        private ToolboxRunner $toolboxRunner,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(AnalysisType $type): bool
    {
        return AnalysisType::CODE_QUALITY === $type;
    }

    public function getName(): string
    {
        return 'hybrid_code_quality_analyzer';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function analyze(AnalysisRequest $request): AnalysisResult
    {
        $this->logger->info('Starting hybrid code quality analysis', [
            'request_id' => $request->id,
            'files_count' => \count($request->files),
        ]);

        // Try AI analysis first
        $aiResult = $this->tryAIAnalysis($request);
        if (null !== $aiResult) {
            $this->logger->info('AI analysis successful', ['request_id' => $request->id]);

            return $aiResult;
        }

        // Fall back to static analysis
        $this->logger->info('Falling back to static analysis', ['request_id' => $request->id]);

        return $this->performStaticAnalysis($request);
    }

    private function tryAIAnalysis(AnalysisRequest $request): ?AnalysisResult
    {
        try {
            $prompt = $this->generateCodeQualityPrompt($request);

            // Try different providers in order of preference
            $providers = ['openai', 'anthropic', 'gemini'];

            foreach ($providers as $provider) {
                try {
                    $response = $this->toolboxRunner->run($provider, null, [
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'temperature' => 0.1,
                        'max_tokens' => 2000,
                    ]);

                    if (!empty($response)) {
                        return $this->parseAIResponse($response, $request);
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning("AI provider {$provider} failed", [
                        'error' => $e->getMessage(),
                        'provider' => $provider,
                    ]);
                    continue; // Try next provider
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('AI analysis completely failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null; // AI analysis failed
    }

    private function generateCodeQualityPrompt(AnalysisRequest $request): string
    {
        $filesContent = '';
        foreach ($request->files as $filePath => $content) {
            $filesContent .= "\n=== File: {$filePath} ===\n{$content}\n";
        }

        return <<<PROMPT
You are a senior PHP developer and code quality expert. Analyze the following PHP code for quality issues and provide suggestions.

Focus on:
- Security vulnerabilities (SQL injection, XSS, etc.)
- PSR compliance and coding standards
- Performance issues
- Maintainability problems
- Best practices violations

Code to analyze:
{$filesContent}

Respond in JSON format:
{
  "summary": "Brief analysis summary",
  "issues": [
    {
      "id": "unique_id",
      "title": "Issue title",
      "description": "Detailed description",
      "severity": "critical|high|medium|low",
      "category": "security|best_practice|performance|complexity",
      "file": "filename",
      "line": 25,
      "fix_suggestion": "How to fix this issue"
    }
  ],
  "suggestions": [
    {
      "id": "suggestion_id",
      "title": "Improvement suggestion",
      "description": "Detailed description",
      "type": "code_cleanup|refactoring|performance_optimization",
      "priority": "high|medium|low"
    }
  ]
}
PROMPT;
    }

    private function parseAIResponse(string $response, AnalysisRequest $request): AnalysisResult
    {
        try {
            // Extract JSON from response (handle cases where AI adds extra text)
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $jsonData = json_decode($matches[0], true, 512, \JSON_THROW_ON_ERROR);
            } else {
                throw new \JsonException('No valid JSON found in response');
            }

            $issues = [];
            foreach ($jsonData['issues'] ?? [] as $issueData) {
                $issues[] = new Issue(
                    id: $issueData['id'] ?? uniqid('ai_issue_'),
                    title: $issueData['title'] ?? 'AI Detected Issue',
                    description: $issueData['description'] ?? '',
                    severity: Severity::from($issueData['severity'] ?? 'medium'),
                    category: IssueCategory::from($issueData['category'] ?? 'best_practice'),
                    file: $issueData['file'] ?? null,
                    line: $issueData['line'] ?? null,
                    fixSuggestion: $issueData['fix_suggestion'] ?? null,
                );
            }

            $suggestions = [];
            foreach ($jsonData['suggestions'] ?? [] as $suggestionData) {
                $suggestions[] = new Suggestion(
                    id: $suggestionData['id'] ?? uniqid('ai_suggestion_'),
                    title: $suggestionData['title'] ?? 'AI Suggestion',
                    description: $suggestionData['description'] ?? '',
                    type: SuggestionType::from($suggestionData['type'] ?? 'code_cleanup'),
                    priority: Priority::from($suggestionData['priority'] ?? 'medium'),
                );
            }

            return new AnalysisResult(
                type: AnalysisType::CODE_QUALITY,
                summary: $jsonData['summary'] ?? 'AI-powered code quality analysis completed',
                issues: $issues,
                suggestions: $suggestions,
                metrics: [
                    'analysis_type' => 'ai_powered',
                    'files_analyzed' => \count($request->files),
                    'ai_issues_found' => \count($issues),
                    'ai_suggestions' => \count($suggestions),
                ],
                overallSeverity: $this->calculateOverallSeverity($issues),
                confidence: 0.9, // High confidence for AI analysis
                analyzedAt: new \DateTimeImmutable(),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to parse AI response', [
                'error' => $e->getMessage(),
                'response_sample' => substr($response, 0, 200),
            ]);

            // Return a basic result indicating AI parsing failed
            return new AnalysisResult(
                type: AnalysisType::CODE_QUALITY,
                summary: 'AI analysis failed to parse response - using fallback',
                issues: [],
                suggestions: [],
                metrics: ['analysis_type' => 'ai_parse_failed'],
                overallSeverity: Severity::INFO,
                confidence: 0.1,
                analyzedAt: new \DateTimeImmutable(),
            );
        }
    }

    private function performStaticAnalysis(AnalysisRequest $request): AnalysisResult
    {
        $issues = [];
        $suggestions = [];

        foreach ($request->files as $filePath => $content) {
            // Static analysis rules
            $this->detectSecurityIssues($content, $filePath, $issues);
            $this->detectCodeQualityIssues($content, $filePath, $issues);
            $this->detectComplexityIssues($content, $filePath, $issues);
        }

        // Add general suggestions
        $suggestions[] = new Suggestion(
            id: 'static_sugg_001',
            title: 'Enable AI Analysis',
            description: 'Configure AI providers for more comprehensive analysis',
            type: SuggestionType::CODE_CLEANUP,
            priority: Priority::HIGH,
            implementation: 'Set up API keys for OpenAI, Anthropic, or Google Gemini',
        );

        return new AnalysisResult(
            type: AnalysisType::CODE_QUALITY,
            summary: \sprintf(
                'Static analysis found %d issues (AI analysis unavailable - check provider configuration)',
                \count($issues)
            ),
            issues: $issues,
            suggestions: $suggestions,
            metrics: [
                'analysis_type' => 'static_fallback',
                'files_analyzed' => \count($request->files),
                'static_issues_found' => \count($issues),
                'ai_available' => false,
            ],
            overallSeverity: $this->calculateOverallSeverity($issues),
            confidence: 0.7, // Lower confidence for static analysis
            analyzedAt: new \DateTimeImmutable(),
        );
    }

    private function detectSecurityIssues(string $content, string $filePath, array &$issues): void
    {
        // SQL injection detection
        if (preg_match('/query\(["\'].*\.\s*\$/', $content)) {
            $issues[] = new Issue(
                id: 'sec_001_'.hash('crc32', $filePath),
                title: 'SQL Injection Vulnerability',
                description: 'Direct SQL string concatenation detected',
                severity: Severity::CRITICAL,
                category: IssueCategory::SECURITY,
                file: $filePath,
                line: $this->findLineNumber($content, 'query('),
                fixSuggestion: 'Use prepared statements with parameter binding',
            );
        }
    }

    private function detectCodeQualityIssues(string $content, string $filePath, array &$issues): void
    {
        // Missing type declarations
        if (preg_match('/public function \w+\([^)]*\)\s*{/', $content)) {
            $issues[] = new Issue(
                id: 'cq_001_'.hash('crc32', $filePath),
                title: 'Missing Type Declarations',
                description: 'Method parameters and return types should be explicitly declared',
                severity: Severity::MEDIUM,
                category: IssueCategory::BEST_PRACTICE,
                file: $filePath,
                line: $this->findLineNumber($content, 'public function'),
                fixSuggestion: 'Add parameter and return type declarations',
            );
        }
    }

    private function detectComplexityIssues(string $content, string $filePath, array &$issues): void
    {
        // Deep nesting detection
        if (preg_match_all('/if\s*\([^{]*\{[^}]*if\s*\([^{]*\{[^}]*if/', $content) > 0) {
            $issues[] = new Issue(
                id: 'comp_001_'.hash('crc32', $filePath),
                title: 'High Cyclomatic Complexity',
                description: 'Deep nested conditions detected',
                severity: Severity::MEDIUM,
                category: IssueCategory::COMPLEXITY,
                file: $filePath,
                line: $this->findLineNumber($content, 'if ('),
                fixSuggestion: 'Extract methods or use early returns to reduce nesting',
            );
        }
    }

    private function findLineNumber(string $content, string $pattern): int
    {
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (str_contains($line, $pattern)) {
                return $lineNumber + 1;
            }
        }

        return 1;
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

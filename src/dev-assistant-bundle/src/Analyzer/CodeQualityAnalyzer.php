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
use Symfony\AI\DevAssistantBundle\Model\Priority;
use Symfony\AI\DevAssistantBundle\Model\Severity;
use Symfony\AI\DevAssistantBundle\Model\Suggestion;
use Symfony\AI\DevAssistantBundle\Model\SuggestionType;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * AI-powered code quality analyzer using advanced language models.
 *
 * This analyzer uses sophisticated AI models to understand code context,
 * identify quality issues, and provide intelligent suggestions for improvement.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class CodeQualityAnalyzer
{
    /**
     * @param array<string> $rules
     */
    public function __construct(
        private PlatformInterface $platform,
        private LoggerInterface $logger,
        private array $rules = [],
    ) {
    }

    /**
     * @param array<string> $rules
     */
    public function analyze(
        string $code,
        ?string $filePath = null,
        array $rules = [],
        string $depth = 'standard',
        bool $includeSuggestions = true,
    ): AnalysisResult {
        $this->logger->info('Starting AI code quality analysis', [
            'file_path' => $filePath,
            'code_length' => \strlen($code),
            'analysis_depth' => $depth,
        ]);

        $startTime = microtime(true);

        // Build sophisticated AI prompt for code analysis
        $prompt = $this->buildAnalysisPrompt($code, $filePath, $rules, $depth, $includeSuggestions);

        // Execute AI analysis using Claude/GPT for deep code understanding
        $messages = new MessageBag(
            Message::forSystem($this->getSystemPrompt($depth)),
            Message::ofUser($prompt)
        );

        $result = $this->platform->invoke(
            model: $this->getOptimalModel($depth),
            messages: $messages,
            options: [
                'temperature' => 0.1, // Low temperature for consistent analysis
                'max_tokens' => $this->getMaxTokensForDepth($depth),
            ]
        )->getResult();

        if (!$result instanceof TextResult) {
            throw new \RuntimeException('Expected TextResult from AI analysis');
        }

        // Parse AI response and extract structured insights
        $analysisData = $this->parseAIResponse($result->getContent());

        $analysisTime = microtime(true) - $startTime;

        $this->logger->info('AI code quality analysis completed', [
            'analysis_time_seconds' => round($analysisTime, 3),
            'issues_found' => \count($analysisData['issues']),
            'suggestions_generated' => \count($analysisData['suggestions']),
        ]);

        return new AnalysisResult(
            type: AnalysisType::CODE_QUALITY,
            summary: $analysisData['summary'],
            issues: $analysisData['issues'],
            suggestions: $analysisData['suggestions'],
            metrics: $analysisData['metrics'],
            overallSeverity: $this->calculateOverallSeverity($analysisData['issues']),
            confidence: $analysisData['confidence'],
            analyzedAt: new \DateTimeImmutable(),
        );
    }

    private function getSystemPrompt(string $depth): string
    {
        $basePrompt = <<<'PROMPT'
You are a senior PHP developer and architect with 15+ years of experience in enterprise software development.
You specialize in code quality analysis, design patterns, and Symfony framework best practices.

Your task is to perform comprehensive code quality analysis with the following expertise:
- PSR-12 coding standards and PHP best practices
- SOLID principles and design patterns
- Symfony framework conventions and anti-patterns
- Performance optimization and security considerations
- Maintainability and code readability
- Error handling and edge case detection

Analyze the provided PHP code and return your findings in the following JSON format:
{
  "summary": "Brief overview of code quality and main findings",
  "issues": [
    {
      "id": "unique_issue_id",
      "title": "Issue title",
      "description": "Detailed explanation of the issue",
      "severity": "critical|high|medium|low|info",
      "category": "code_style|maintainability|performance|security|architecture|testing|documentation|dependency|compatibility|best_practice",
      "file": "file path if available",
      "line": line_number,
      "column": column_number,
      "rule": "specific rule or principle violated",
      "fixSuggestion": "How to fix this issue",
      "codeSnippet": "relevant code snippet",
      "reasoning": "Why this is an issue and its impact"
    }
  ],
  "suggestions": [
    {
      "id": "unique_suggestion_id",
      "title": "Improvement suggestion",
      "description": "Detailed explanation",
      "type": "refactoring|optimization|security_improvement|architectural_change|testing_improvement|documentation|dependency_upgrade|code_cleanup",
      "priority": "urgent|high|medium|low",
      "implementation": "Step-by-step implementation guide",
      "reasoning": "Why this improvement is beneficial",
      "exampleCode": "Example of improved code",
      "benefits": ["benefit1", "benefit2"],
      "estimatedImpact": 0.8
    }
  ],
  "metrics": {
    "complexity_score": 7.5,
    "maintainability_index": 85,
    "technical_debt_minutes": 45,
    "code_coverage_estimate": 0.7
  },
  "confidence": 0.9
}
PROMPT;

        return match ($depth) {
            'expert' => $basePrompt."\n\nPerform EXPERT-level analysis with deep architectural insights and advanced pattern recognition.",
            'comprehensive' => $basePrompt."\n\nPerform COMPREHENSIVE analysis covering all aspects thoroughly.",
            'basic' => $basePrompt."\n\nPerform BASIC analysis focusing on critical issues and obvious improvements.",
            default => $basePrompt."\n\nPerform STANDARD analysis with balanced depth and practical insights.",
        };
    }

    /**
     * @param array<string> $rules
     */
    private function buildAnalysisPrompt(
        string $code,
        ?string $filePath,
        array $rules,
        string $depth,
        bool $includeSuggestions,
    ): string {
        $prompt = "Please analyze the following PHP code:\n\n";

        if ($filePath) {
            $prompt .= "File: {$filePath}\n\n";
        }

        $prompt .= "```php\n{$code}\n```\n\n";

        $prompt .= "Focus areas:\n";
        foreach ($rules as $rule) {
            $prompt .= '- '.$this->getRuleDescription($rule)."\n";
        }

        $prompt .= "\nAnalysis depth: {$depth}\n";

        if (!$includeSuggestions) {
            $prompt .= "\nNote: Focus on identifying issues. Minimize suggestions.\n";
        }

        $prompt .= "\nProvide thorough analysis with practical, actionable insights.";

        return $prompt;
    }

    private function getRuleDescription(string $rule): string
    {
        return match ($rule) {
            'psr12' => 'PSR-12 coding standards compliance',
            'solid' => 'SOLID principles adherence',
            'symfony_standards' => 'Symfony framework best practices',
            'phpstan_level_8' => 'Strict type safety and static analysis',
            'design_patterns' => 'Proper design pattern implementation',
            'performance' => 'Performance optimization opportunities',
            'security' => 'Security vulnerabilities and best practices',
            default => ucfirst(str_replace('_', ' ', $rule)),
        };
    }

    private function getOptimalModel(string $depth): string
    {
        // Use different models based on analysis depth for optimal results
        return match ($depth) {
            'expert', 'comprehensive' => 'claude-3-5-sonnet-20241022', // Best for deep analysis
            'basic' => 'gpt-4o-mini', // Faster for basic checks
            default => 'claude-3-5-haiku-20241022', // Balanced for standard analysis
        };
    }

    private function getMaxTokensForDepth(string $depth): int
    {
        return match ($depth) {
            'expert' => 4000,
            'comprehensive' => 3000,
            'basic' => 1500,
            default => 2500,
        };
    }

    /**
     * @return array{summary: string, issues: array<Issue>, suggestions: array<Suggestion>, metrics: array<string, mixed>, confidence: float}
     */
    private function parseAIResponse(string $response): array
    {
        // Extract JSON from AI response (it might have additional text)
        $jsonMatch = [];
        if (preg_match('/\{.*\}/s', $response, $jsonMatch)) {
            $jsonContent = $jsonMatch[0];
        } else {
            // Fallback: try to parse the entire response as JSON
            $jsonContent = $response;
        }

        try {
            $data = json_decode($jsonContent, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Failed to parse AI response as JSON', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);

            // Return fallback analysis
            return [
                'summary' => 'AI analysis completed but response parsing failed',
                'issues' => [],
                'suggestions' => [],
                'metrics' => ['parse_error' => true],
                'confidence' => 0.0,
            ];
        }

        return [
            'summary' => $data['summary'] ?? 'No summary provided',
            'issues' => $this->parseIssues($data['issues'] ?? []),
            'suggestions' => $this->parseSuggestions($data['suggestions'] ?? []),
            'metrics' => $data['metrics'] ?? [],
            'confidence' => (float) ($data['confidence'] ?? 0.8),
        ];
    }

    /**
     * @param array<array<string, mixed>> $issuesData
     *
     * @return array<Issue>
     */
    private function parseIssues(array $issuesData): array
    {
        $issues = [];

        foreach ($issuesData as $issueData) {
            try {
                $issues[] = new Issue(
                    id: $issueData['id'] ?? uniqid('issue_'),
                    title: $issueData['title'] ?? 'Unknown issue',
                    description: $issueData['description'] ?? '',
                    severity: Severity::from($issueData['severity'] ?? 'medium'),
                    category: IssueCategory::from($issueData['category'] ?? 'best_practice'),
                    file: $issueData['file'] ?? null,
                    line: $issueData['line'] ?? null,
                    column: $issueData['column'] ?? null,
                    rule: $issueData['rule'] ?? null,
                    fixSuggestion: $issueData['fixSuggestion'] ?? null,
                    codeSnippet: $issueData['codeSnippet'] ?? null,
                    metadata: [
                        'reasoning' => $issueData['reasoning'] ?? null,
                        'ai_generated' => true,
                    ],
                );
            } catch (\ValueError $e) {
                $this->logger->warning('Invalid issue data from AI response', [
                    'issue_data' => $issueData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $issues;
    }

    /**
     * @param array<array<string, mixed>> $suggestionsData
     *
     * @return array<Suggestion>
     */
    private function parseSuggestions(array $suggestionsData): array
    {
        $suggestions = [];

        foreach ($suggestionsData as $suggestionData) {
            try {
                $suggestions[] = new Suggestion(
                    id: $suggestionData['id'] ?? uniqid('suggestion_'),
                    title: $suggestionData['title'] ?? 'Improvement suggestion',
                    description: $suggestionData['description'] ?? '',
                    type: SuggestionType::from($suggestionData['type'] ?? 'code_cleanup'),
                    priority: Priority::from($suggestionData['priority'] ?? 'medium'),
                    implementation: $suggestionData['implementation'] ?? null,
                    reasoning: $suggestionData['reasoning'] ?? null,
                    exampleCode: $suggestionData['exampleCode'] ?? null,
                    benefits: $suggestionData['benefits'] ?? [],
                    estimatedImpact: $suggestionData['estimatedImpact'] ?? null,
                    metadata: [
                        'ai_generated' => true,
                    ],
                );
            } catch (\ValueError $e) {
                $this->logger->warning('Invalid suggestion data from AI response', [
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

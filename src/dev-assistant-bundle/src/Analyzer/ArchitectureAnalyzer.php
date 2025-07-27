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
 * AI-powered architecture analyzer that evaluates system design and structure.
 *
 * Uses advanced AI to analyze architectural patterns, dependencies, design decisions,
 * and provide strategic recommendations for scalable and maintainable systems.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class ArchitectureAnalyzer
{
    public function __construct(
        private PlatformInterface $platform,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string> $filePaths
     */
    public function analyzeArchitecture(
        array $filePaths,
        string $projectRoot,
        string $depth = 'standard',
    ): AnalysisResult {
        $this->logger->info('Starting AI architecture analysis', [
            'files_count' => \count($filePaths),
            'project_root' => $projectRoot,
            'analysis_depth' => $depth,
        ]);

        $startTime = microtime(true);

        // Read and analyze project structure
        $projectStructure = $this->buildProjectStructure($filePaths, $projectRoot);
        $codebase = $this->extractCodebaseContext($filePaths);

        // Build sophisticated architectural analysis prompt
        $prompt = $this->buildArchitecturalPrompt($projectStructure, $codebase, $depth);

        $messages = new MessageBag(
            Message::forSystem($this->getArchitecturalSystemPrompt($depth)),
            Message::ofUser($prompt)
        );

        $result = $this->platform->invoke(
            model: 'claude-3-5-sonnet-20241022', // Best for architectural reasoning
            messages: $messages,
            options: [
                'temperature' => 0.2,
                'max_tokens' => 4000,
            ]
        )->getResult();

        if (!$result instanceof TextResult) {
            throw new \RuntimeException('Expected TextResult from architectural analysis');
        }

        $analysisData = $this->parseArchitecturalResponse($result->getContent());

        $analysisTime = microtime(true) - $startTime;

        $this->logger->info('AI architecture analysis completed', [
            'analysis_time_seconds' => round($analysisTime, 3),
            'architectural_issues' => \count($analysisData['issues']),
            'strategic_recommendations' => \count($analysisData['suggestions']),
        ]);

        return new AnalysisResult(
            type: AnalysisType::ARCHITECTURE,
            summary: $analysisData['summary'],
            issues: $analysisData['issues'],
            suggestions: $analysisData['suggestions'],
            metrics: $analysisData['metrics'],
            overallSeverity: $this->calculateOverallSeverity($analysisData['issues']),
            confidence: $analysisData['confidence'],
            analyzedAt: new \DateTimeImmutable(),
        );
    }

    private function getArchitecturalSystemPrompt(string $depth): string
    {
        return <<<'PROMPT'
You are a senior software architect with 15+ years of experience in enterprise system design.
Your expertise includes:

- Domain-Driven Design (DDD) and Clean Architecture
- Microservices and distributed systems architecture
- SOLID principles and design patterns
- Scalability and performance architecture
- Security architecture and threat modeling
- Legacy system modernization
- Technology stack evaluation and decision making
- System integration patterns and API design

Analyze the provided codebase architecture and return findings in this JSON format:

{
  "summary": "Executive summary of architectural state and key findings",
  "issues": [
    {
      "id": "arch_issue_id",
      "title": "Architectural issue title",
      "description": "Detailed analysis of the architectural problem",
      "severity": "critical|high|medium|low|info",
      "category": "architecture",
      "rule": "architectural principle violated",
      "reasoning": "Impact on scalability, maintainability, and system quality",
      "fixSuggestion": "Strategic approach to resolve the issue",
      "estimatedEffort": "development time estimate"
    }
  ],
  "suggestions": [
    {
      "id": "arch_suggestion_id",
      "title": "Strategic improvement recommendation",
      "description": "Detailed architectural improvement proposal",
      "type": "architectural_change",
      "priority": "urgent|high|medium|low",
      "implementation": "Step-by-step architectural transformation plan",
      "reasoning": "Business and technical benefits",
      "benefits": ["scalability", "maintainability", "performance"],
      "estimatedImpact": 0.9,
      "migrationStrategy": "Approach for safe implementation"
    }
  ],
  "metrics": {
    "coupling_score": 6.5,
    "cohesion_score": 8.2,
    "complexity_index": 7.1,
    "dependency_violations": 3,
    "architectural_debt_hours": 120,
    "testability_score": 7.8,
    "modularity_index": 8.5
  },
  "confidence": 0.85
}

Focus on strategic architectural decisions, system boundaries, dependency management, and long-term maintainability.
PROMPT;
    }

    /**
     * @param array<string> $filePaths
     *
     * @return array<string, mixed>
     */
    private function buildProjectStructure(array $filePaths, string $projectRoot): array
    {
        $structure = [
            'directories' => [],
            'namespace_analysis' => [],
            'dependency_graph' => [],
            'file_organization' => [],
        ];

        foreach ($filePaths as $filePath) {
            $relativePath = str_replace($projectRoot.\DIRECTORY_SEPARATOR, '', $filePath);
            $pathParts = explode(\DIRECTORY_SEPARATOR, $relativePath);

            // Build directory hierarchy
            $currentLevel = &$structure['directories'];
            foreach ($pathParts as $part) {
                if (!isset($currentLevel[$part])) {
                    $currentLevel[$part] = [];
                }
                $currentLevel = &$currentLevel[$part];
            }

            // Analyze namespace structure if PHP file
            if (str_ends_with($filePath, '.php') && is_readable($filePath)) {
                $content = file_get_contents($filePath);
                if (false !== $content) {
                    $namespace = $this->extractNamespace($content);
                    if ($namespace) {
                        $structure['namespace_analysis'][] = [
                            'file' => $relativePath,
                            'namespace' => $namespace,
                            'classes' => $this->extractClasses($content),
                            'dependencies' => $this->extractDependencies($content),
                        ];
                    }
                }
            }
        }

        return $structure;
    }

    /**
     * @param array<string> $filePaths
     *
     * @return array<string, mixed>
     */
    private function extractCodebaseContext(array $filePaths): array
    {
        $context = [
            'total_files' => \count($filePaths),
            'file_types' => [],
            'size_analysis' => [],
            'key_files' => [],
        ];

        $totalSize = 0;
        $phpFiles = 0;

        foreach ($filePaths as $filePath) {
            if (!is_readable($filePath)) {
                continue;
            }

            $extension = pathinfo($filePath, \PATHINFO_EXTENSION);
            $context['file_types'][$extension] = ($context['file_types'][$extension] ?? 0) + 1;

            $fileSize = filesize($filePath);
            $totalSize += $fileSize;

            if ('php' === $extension) {
                ++$phpFiles;

                // Identify key architectural files
                $filename = basename($filePath);
                if (preg_match('/(Controller|Service|Repository|Entity|Bundle|Kernel)\.php$/', $filename)) {
                    $context['key_files'][] = $filePath;
                }
            }
        }

        $context['size_analysis'] = [
            'total_size_bytes' => $totalSize,
            'average_file_size' => $totalSize > 0 ? (int) ($totalSize / \count($filePaths)) : 0,
            'php_files_count' => $phpFiles,
            'php_ratio' => \count($filePaths) > 0 ? round($phpFiles / \count($filePaths), 2) : 0,
        ];

        return $context;
    }

    /**
     * @param array<string, mixed> $projectStructure
     * @param array<string, mixed> $codebase
     */
    private function buildArchitecturalPrompt(array $projectStructure, array $codebase, string $depth): string
    {
        $prompt = "Please perform architectural analysis of this PHP/Symfony project:\n\n";

        $prompt .= "## Project Structure\n";
        $prompt .= "Directory hierarchy:\n";
        $prompt .= $this->formatDirectoryTree($projectStructure['directories']);
        $prompt .= "\n\n";

        $prompt .= "## Codebase Overview\n";
        $prompt .= \sprintf("- Total files: %d\n", $codebase['total_files']);
        $prompt .= \sprintf("- PHP files: %d (%.1f%%)\n",
            $codebase['size_analysis']['php_files_count'],
            $codebase['size_analysis']['php_ratio'] * 100
        );
        $prompt .= \sprintf("- Total size: %.1f KB\n", $codebase['size_analysis']['total_size_bytes'] / 1024);

        if (!empty($codebase['key_files'])) {
            $prompt .= "\n## Key Architectural Files\n";
            foreach (\array_slice($codebase['key_files'], 0, 10) as $file) {
                $prompt .= '- '.basename($file)."\n";
            }
        }

        if (!empty($projectStructure['namespace_analysis'])) {
            $prompt .= "\n## Namespace Analysis\n";
            foreach (\array_slice($projectStructure['namespace_analysis'], 0, 15) as $ns) {
                $prompt .= \sprintf("- %s: %s\n", $ns['file'], $ns['namespace']);
                if (!empty($ns['classes'])) {
                    $prompt .= '  Classes: '.implode(', ', $ns['classes'])."\n";
                }
            }
        }

        $prompt .= "\n## Analysis Focus\n";
        $prompt .= match ($depth) {
            'expert' => 'Perform EXPERT architectural analysis with deep system design insights, scalability assessment, and strategic modernization recommendations.',
            'comprehensive' => 'Perform COMPREHENSIVE architectural review covering system design, patterns, dependencies, and improvement opportunities.',
            'basic' => 'Perform BASIC architectural assessment focusing on critical structural issues and immediate improvements.',
            default => 'Perform STANDARD architectural analysis with practical insights on design quality and maintainability.',
        };

        $prompt .= "\n\nEvaluate: system boundaries, dependency management, design patterns usage, scalability concerns, and architectural technical debt.";

        return $prompt;
    }

    /**
     * @param array<string, mixed> $directories
     */
    private function formatDirectoryTree(array $directories, int $level = 0): string
    {
        $tree = '';
        $indent = str_repeat('  ', $level);

        foreach ($directories as $name => $children) {
            $tree .= $indent.$name."/\n";
            if (\is_array($children) && !empty($children) && $level < 3) {
                $tree .= $this->formatDirectoryTree($children, $level + 1);
            }
        }

        return $tree;
    }

    private function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array<string>
     */
    private function extractClasses(string $content): array
    {
        $classes = [];
        if (preg_match_all('/(?:class|interface|trait)\s+(\w+)/', $content, $matches)) {
            $classes = $matches[1];
        }

        return $classes;
    }

    /**
     * @return array<string>
     */
    private function extractDependencies(string $content): array
    {
        $dependencies = [];
        if (preg_match_all('/use\s+([^;]+);/', $content, $matches)) {
            $dependencies = $matches[1];
        }

        return $dependencies;
    }

    /**
     * @return array{summary: string, issues: array<Issue>, suggestions: array<Suggestion>, metrics: array<string, mixed>, confidence: float}
     */
    private function parseArchitecturalResponse(string $response): array
    {
        $jsonMatch = [];
        if (preg_match('/\{.*\}/s', $response, $jsonMatch)) {
            $jsonContent = $jsonMatch[0];
        } else {
            $jsonContent = $response;
        }

        try {
            $data = json_decode($jsonContent, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Failed to parse architectural AI response', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);

            return [
                'summary' => 'Architectural analysis completed but response parsing failed',
                'issues' => [],
                'suggestions' => [],
                'metrics' => ['parse_error' => true],
                'confidence' => 0.0,
            ];
        }

        return [
            'summary' => $data['summary'] ?? 'No architectural summary provided',
            'issues' => $this->parseArchitecturalIssues($data['issues'] ?? []),
            'suggestions' => $this->parseArchitecturalSuggestions($data['suggestions'] ?? []),
            'metrics' => $data['metrics'] ?? [],
            'confidence' => (float) ($data['confidence'] ?? 0.8),
        ];
    }

    /**
     * @param array<array<string, mixed>> $issuesData
     *
     * @return array<Issue>
     */
    private function parseArchitecturalIssues(array $issuesData): array
    {
        $issues = [];

        foreach ($issuesData as $issueData) {
            try {
                $issues[] = new Issue(
                    id: $issueData['id'] ?? uniqid('arch_issue_'),
                    title: $issueData['title'] ?? 'Architectural issue',
                    description: $issueData['description'] ?? '',
                    severity: Severity::from($issueData['severity'] ?? 'medium'),
                    category: IssueCategory::ARCHITECTURE,
                    file: $issueData['file'] ?? null,
                    line: $issueData['line'] ?? null,
                    column: $issueData['column'] ?? null,
                    rule: $issueData['rule'] ?? 'architectural_principle',
                    fixSuggestion: $issueData['fixSuggestion'] ?? null,
                    codeSnippet: $issueData['codeSnippet'] ?? null,
                    metadata: [
                        'reasoning' => $issueData['reasoning'] ?? null,
                        'estimated_effort' => $issueData['estimatedEffort'] ?? null,
                        'ai_generated' => true,
                        'analysis_type' => 'architecture',
                    ],
                );
            } catch (\ValueError $e) {
                $this->logger->warning('Invalid architectural issue data', [
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
    private function parseArchitecturalSuggestions(array $suggestionsData): array
    {
        $suggestions = [];

        foreach ($suggestionsData as $suggestionData) {
            try {
                $suggestions[] = new Suggestion(
                    id: $suggestionData['id'] ?? uniqid('arch_suggestion_'),
                    title: $suggestionData['title'] ?? 'Architectural improvement',
                    description: $suggestionData['description'] ?? '',
                    type: SuggestionType::ARCHITECTURAL_CHANGE,
                    priority: Priority::from($suggestionData['priority'] ?? 'medium'),
                    implementation: $suggestionData['implementation'] ?? null,
                    reasoning: $suggestionData['reasoning'] ?? null,
                    exampleCode: $suggestionData['exampleCode'] ?? null,
                    benefits: $suggestionData['benefits'] ?? [],
                    estimatedImpact: $suggestionData['estimatedImpact'] ?? null,
                    metadata: [
                        'migration_strategy' => $suggestionData['migrationStrategy'] ?? null,
                        'ai_generated' => true,
                        'analysis_type' => 'architecture',
                    ],
                );
            } catch (\ValueError $e) {
                $this->logger->warning('Invalid architectural suggestion data', [
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

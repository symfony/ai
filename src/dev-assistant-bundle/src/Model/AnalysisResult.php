<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\DevAssistantBundle\Model;

/**
 * Represents the result of a code analysis operation.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class AnalysisResult
{
    /**
     * @param array<Issue> $issues
     * @param array<Suggestion> $suggestions
     * @param array<string, mixed> $metrics
     */
    public function __construct(
        public AnalysisType $type,
        public string $summary,
        public array $issues,
        public array $suggestions,
        public array $metrics,
        public Severity $overallSeverity,
        public float $confidence,
        public \DateTimeImmutable $analyzedAt,
    ) {
    }

    public function hasIssues(): bool
    {
        return !empty($this->issues);
    }

    public function hasCriticalIssues(): bool
    {
        foreach ($this->issues as $issue) {
            if (Severity::CRITICAL === $issue->severity) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<Issue>
     */
    public function getIssuesBySeverity(Severity $severity): array
    {
        return array_filter(
            $this->issues,
            fn (Issue $issue) => $issue->severity === $severity
        );
    }

    public function getScore(): float
    {
        if (empty($this->issues)) {
            return 10.0;
        }

        $totalPenalty = 0.0;
        foreach ($this->issues as $issue) {
            $totalPenalty += $issue->severity->getPenalty();
        }

        return max(0.0, 10.0 - $totalPenalty);
    }
}

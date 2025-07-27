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
 * Enumeration of analysis types with specific AI model requirements.
 *
 * This enum defines the different types of analysis that can be performed,
 * each with specific characteristics that may require different AI models
 * or processing strategies.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
enum AnalysisType: string
{
    case CODE_QUALITY = 'code_quality';
    case ARCHITECTURE = 'architecture';
    case PERFORMANCE = 'performance';
    case SECURITY = 'security';
    case DOCUMENTATION = 'documentation';
    case TESTING = 'testing';
    case REFACTORING = 'refactoring';

    /**
     * Get the display name for this analysis type.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::CODE_QUALITY => 'Code Quality Analysis',
            self::ARCHITECTURE => 'Architecture Review',
            self::PERFORMANCE => 'Performance Analysis',
            self::SECURITY => 'Security Assessment',
            self::DOCUMENTATION => 'Documentation Review',
            self::TESTING => 'Test Coverage Analysis',
            self::REFACTORING => 'Refactoring Suggestions',
        };
    }

    /**
     * Get the expected complexity of this analysis type.
     * 
     * Higher complexity may require more sophisticated AI models.
     */
    public function getComplexity(): int
    {
        return match ($this) {
            self::CODE_QUALITY => 3,
            self::ARCHITECTURE => 5,
            self::PERFORMANCE => 4,
            self::SECURITY => 5,
            self::DOCUMENTATION => 2,
            self::TESTING => 3,
            self::REFACTORING => 4,
        };
    }

    /**
     * Check if this analysis type requires advanced reasoning capabilities.
     */
    public function requiresAdvancedReasoning(): bool
    {
        return match ($this) {
            self::ARCHITECTURE, self::SECURITY, self::REFACTORING => true,
            default => false,
        };
    }

    /**
     * Get the recommended minimum model capability level (1-10).
     */
    public function getRecommendedModelLevel(): int
    {
        return match ($this) {
            self::DOCUMENTATION => 4,
            self::CODE_QUALITY, self::TESTING => 6,
            self::PERFORMANCE, self::REFACTORING => 7,
            self::ARCHITECTURE, self::SECURITY => 9,
        };
    }

    /**
     * Get the typical analysis timeout in seconds.
     */
    public function getTypicalTimeout(): int
    {
        return match ($this) {
            self::DOCUMENTATION => 30,
            self::CODE_QUALITY, self::TESTING => 60,
            self::PERFORMANCE, self::REFACTORING => 90,
            self::ARCHITECTURE, self::SECURITY => 120,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CODE_QUALITY => 'Analyzes code style, maintainability, and best practices with advanced pattern recognition',
            self::ARCHITECTURE => 'Evaluates design patterns, SOLID principles, and architectural decisions using deep reasoning',
            self::PERFORMANCE => 'Identifies performance bottlenecks and optimization opportunities through code analysis',
            self::SECURITY => 'Scans for security vulnerabilities and compliance issues with threat modeling',
            self::DOCUMENTATION => 'Reviews documentation quality, completeness, and clarity',
            self::TESTING => 'Analyzes test coverage, quality, and identifies missing test scenarios',
            self::REFACTORING => 'Suggests code improvements and refactoring opportunities with impact analysis',
        };
    }

    /**
     * @return array<string>
     */
    public function getRequiredCapabilities(): array
    {
        return match ($this) {
            self::CODE_QUALITY => ['code_analysis', 'pattern_recognition', 'best_practices'],
            self::ARCHITECTURE => ['design_patterns', 'system_design', 'architectural_reasoning'],
            self::PERFORMANCE => ['performance_analysis', 'optimization', 'profiling'],
            self::SECURITY => ['security_analysis', 'threat_modeling', 'vulnerability_detection'],
            self::DOCUMENTATION => ['natural_language', 'documentation_standards'],
            self::TESTING => ['test_analysis', 'coverage_analysis', 'test_strategy'],
            self::REFACTORING => ['code_transformation', 'impact_analysis', 'refactoring_patterns'],
        };
    }
}

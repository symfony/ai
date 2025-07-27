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
 * Categorizes different types of issues that can be found during analysis.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
enum IssueCategory: string
{
    case CODE_STYLE = 'code_style';
    case MAINTAINABILITY = 'maintainability';
    case PERFORMANCE = 'performance';
    case SECURITY = 'security';
    case ARCHITECTURE = 'architecture';
    case TESTING = 'testing';
    case DOCUMENTATION = 'documentation';
    case DEPENDENCY = 'dependency';
    case COMPATIBILITY = 'compatibility';
    case BEST_PRACTICE = 'best_practice';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::CODE_STYLE => 'Code Style',
            self::MAINTAINABILITY => 'Maintainability',
            self::PERFORMANCE => 'Performance',
            self::SECURITY => 'Security',
            self::ARCHITECTURE => 'Architecture',
            self::TESTING => 'Testing',
            self::DOCUMENTATION => 'Documentation',
            self::DEPENDENCY => 'Dependencies',
            self::COMPATIBILITY => 'Compatibility',
            self::BEST_PRACTICE => 'Best Practices',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::CODE_STYLE => '🎨',
            self::MAINTAINABILITY => '🔧',
            self::PERFORMANCE => '⚡',
            self::SECURITY => '🔒',
            self::ARCHITECTURE => '🏗️',
            self::TESTING => '🧪',
            self::DOCUMENTATION => '📚',
            self::DEPENDENCY => '📦',
            self::COMPATIBILITY => '🔄',
            self::BEST_PRACTICE => '⭐',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CODE_STYLE => 'Issues related to code formatting, naming conventions, and style guidelines',
            self::MAINTAINABILITY => 'Issues that affect code readability, complexity, and long-term maintenance',
            self::PERFORMANCE => 'Issues that impact application performance and resource usage',
            self::SECURITY => 'Security vulnerabilities and potential attack vectors',
            self::ARCHITECTURE => 'Architectural design issues and pattern violations',
            self::TESTING => 'Missing or inadequate test coverage and testing practices',
            self::DOCUMENTATION => 'Missing or outdated documentation and comments',
            self::DEPENDENCY => 'Issues with package dependencies and version management',
            self::COMPATIBILITY => 'Compatibility issues with PHP versions or frameworks',
            self::BEST_PRACTICE => 'Violations of established best practices and conventions',
        };
    }
}

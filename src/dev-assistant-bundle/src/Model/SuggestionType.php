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
 * Types of suggestions that can be made.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
enum SuggestionType: string
{
    case REFACTORING = 'refactoring';
    case OPTIMIZATION = 'optimization';
    case SECURITY_IMPROVEMENT = 'security_improvement';
    case ARCHITECTURAL_CHANGE = 'architectural_change';
    case TESTING_IMPROVEMENT = 'testing_improvement';
    case DOCUMENTATION = 'documentation';
    case DEPENDENCY_UPGRADE = 'dependency_upgrade';
    case CODE_CLEANUP = 'code_cleanup';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::REFACTORING => 'Refactoring',
            self::OPTIMIZATION => 'Performance Optimization',
            self::SECURITY_IMPROVEMENT => 'Security Enhancement',
            self::ARCHITECTURAL_CHANGE => 'Architecture Improvement',
            self::TESTING_IMPROVEMENT => 'Testing Enhancement',
            self::DOCUMENTATION => 'Documentation',
            self::DEPENDENCY_UPGRADE => 'Dependency Update',
            self::CODE_CLEANUP => 'Code Cleanup',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::REFACTORING => '♻️',
            self::OPTIMIZATION => '🚀',
            self::SECURITY_IMPROVEMENT => '🛡️',
            self::ARCHITECTURAL_CHANGE => '🏛️',
            self::TESTING_IMPROVEMENT => '🔬',
            self::DOCUMENTATION => '📝',
            self::DEPENDENCY_UPGRADE => '📈',
            self::CODE_CLEANUP => '🧹',
        };
    }
}

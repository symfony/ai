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
 * Represents the severity level of an analysis issue.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
enum Severity: string
{
    case INFO = 'info';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::INFO => 'Info',
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::CRITICAL => 'Critical',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::INFO => '#17a2b8',      // Bootstrap info
            self::LOW => '#28a745',       // Bootstrap success
            self::MEDIUM => '#ffc107',    // Bootstrap warning
            self::HIGH => '#fd7e14',      // Bootstrap orange
            self::CRITICAL => '#dc3545',  // Bootstrap danger
        };
    }

    public function getPriority(): int
    {
        return match ($this) {
            self::INFO => 1,
            self::LOW => 2,
            self::MEDIUM => 3,
            self::HIGH => 4,
            self::CRITICAL => 5,
        };
    }

    public function getPenalty(): float
    {
        return match ($this) {
            self::INFO => 0.0,
            self::LOW => 0.1,
            self::MEDIUM => 0.5,
            self::HIGH => 1.0,
            self::CRITICAL => 2.0,
        };
    }

    public function requiresImmediateAction(): bool
    {
        return self::CRITICAL === $this || self::HIGH === $this;
    }
}

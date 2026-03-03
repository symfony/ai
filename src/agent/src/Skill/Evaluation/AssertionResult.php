<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation;

/**
 * Represents the grading result of a single assertion.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AssertionResult
{
    public function __construct(
        private readonly string $text,
        private readonly bool $passed,
        private readonly string $evidence,
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function getEvidence(): string
    {
        return $this->evidence;
    }

    /**
     * @return array{text: string, passed: bool, evidence: string}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'passed' => $this->passed,
            'evidence' => $this->evidence,
        ];
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Guardrail;

/**
 * Represents the result of a guardrail scan.
 *
 * @author Abderrahman Daif <daif.abderrahman@gmail.com>
 */
final class GuardrailResult
{
    /**
     * @param bool        $triggered whether the guardrail tripwire was triggered
     * @param string|null $reason    the reason the guardrail was triggered
     * @param float       $score     a confidence score between 0.0 and 1.0
     * @param string|null $scanner   the name of the scanner that produced this result
     */
    public function __construct(
        private readonly bool $triggered,
        private readonly ?string $reason = null,
        private readonly float $score = 0.0,
        private readonly ?string $scanner = null,
    ) {
    }

    public static function pass(): self
    {
        return new self(false);
    }

    public static function block(string $scanner, string $reason, float $score = 1.0): self
    {
        return new self(true, $reason, $score, $scanner);
    }

    public function isTriggered(): bool
    {
        return $this->triggered;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getScanner(): ?string
    {
        return $this->scanner;
    }
}

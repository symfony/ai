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
 * Result of running a single eval case against an agent.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EvalRunResult
{
    public function __construct(
        private readonly EvalCase $evalCase,
        private readonly string $output,
        private readonly TimingResult $timing,
        private readonly ?GradingResultInterface $grading = null,
    ) {
    }

    public function getEvalCase(): EvalCase
    {
        return $this->evalCase;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getTiming(): TimingResult
    {
        return $this->timing;
    }

    public function getGrading(): ?GradingResultInterface
    {
        return $this->grading;
    }

    public function withGrading(GradingResultInterface $grading): self
    {
        return new self($this->evalCase, $this->output, $this->timing, $grading);
    }
}

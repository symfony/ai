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
 * Represents a complete evaluation suite loaded from evals/evals.json.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EvalSuite
{
    /**
     * @param string     $skillName The skill this suite evaluates
     * @param EvalCase[] $evals     The evaluation cases
     */
    public function __construct(
        private readonly string $skillName,
        private readonly array $evals,
    ) {
    }

    public function getSkillName(): string
    {
        return $this->skillName;
    }

    /**
     * @return EvalCase[]
     */
    public function getEvals(): array
    {
        return $this->evals;
    }

    public function getEvalById(int $id): ?EvalCase
    {
        foreach ($this->evals as $eval) {
            if ($eval->getId() === $id) {
                return $eval;
            }
        }

        return null;
    }
}

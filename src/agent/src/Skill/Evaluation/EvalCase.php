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
 * Represents a single evaluation test case from an evals.json file.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EvalCase
{
    /**
     * @param int      $id             Unique identifier within the eval suite
     * @param string   $prompt         The user prompt to send to the agent
     * @param string   $expectedOutput The expected output for comparison
     * @param string[] $files          Optional files to provide as context
     * @param string[] $assertions     Assertions to grade the output against
     */
    public function __construct(
        private readonly int $id,
        private readonly string $prompt,
        private readonly string $expectedOutput,
        private readonly array $files = [],
        private readonly array $assertions = [],
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getExpectedOutput(): string
    {
        return $this->expectedOutput;
    }

    /**
     * @return string[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return string[]
     */
    public function getAssertions(): array
    {
        return $this->assertions;
    }
}

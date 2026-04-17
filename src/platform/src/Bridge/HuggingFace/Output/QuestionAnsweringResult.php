<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Output;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class QuestionAnsweringResult
{
    public function __construct(
        private readonly string $answer,
        private readonly int $startIndex,
        private readonly int $endIndex,
        private readonly float $score,
    ) {
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    public function getEndIndex(): int
    {
        return $this->endIndex;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * @param array{answer: string, start: int, end: int, score: float} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['answer'],
            $data['start'],
            $data['end'],
            $data['score'],
        );
    }
}

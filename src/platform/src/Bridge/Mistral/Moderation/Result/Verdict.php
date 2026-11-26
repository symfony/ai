<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Moderation\Result;

/**
 * What the moderation model found in a single text or conversation.
 *
 * @author Anthony Hacart <anthony.hacart@gmail.com>
 */
final class Verdict
{
    /**
     * @param array<string, bool>  $categories Whether each category is over the threshold Mistral set for it
     * @param array<string, float> $scores     Score of each category, from 0 to 1
     */
    public function __construct(
        private readonly array $categories,
        private readonly array $scores,
    ) {
    }

    /**
     * @return array<string, bool>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @return array<string, float>
     */
    public function getScores(): array
    {
        return $this->scores;
    }

    public function getScore(string $category): ?float
    {
        return $this->scores[$category] ?? null;
    }

    public function isFlagged(): bool
    {
        return [] !== $this->getFlaggedCategories();
    }

    /**
     * @return list<string>
     */
    public function getFlaggedCategories(): array
    {
        return array_keys(array_filter($this->categories));
    }
}

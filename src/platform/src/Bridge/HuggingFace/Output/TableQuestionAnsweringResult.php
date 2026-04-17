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
final class TableQuestionAnsweringResult
{
    /**
     * @param array{0: int, 1: int}[]   $coordinates
     * @param array<int, string|int>    $cells
     * @param array<string>|string|null $aggregator
     */
    public function __construct(
        private readonly string $answer,
        private readonly array $coordinates = [],
        private readonly array $cells = [],
        private readonly array|string|null $aggregator = null,
    ) {
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    /**
     * @return array{0: int, 1: int}[]
     */
    public function getCoordinates(): array
    {
        return $this->coordinates;
    }

    /**
     * @return array<int, string|int>
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    /**
     * @return array<string>|string|null
     */
    public function getAggregator(): array|string|null
    {
        return $this->aggregator;
    }

    /**
     * @param array{
     *     answer: string,
     *     coordinates?: array{0: int, 1: int}[],
     *     cells?: array<int, string|int>,
     *     aggregator?: array<string>
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['answer'],
            $data['coordinates'] ?? [],
            $data['cells'] ?? [],
            $data['aggregator'] ?? null,
        );
    }
}

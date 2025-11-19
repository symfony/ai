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
        public readonly string $answer,
        public readonly array $coordinates = [],
        public readonly array $cells = [],
        public readonly array|string|null $aggregator = null,
    ) {
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

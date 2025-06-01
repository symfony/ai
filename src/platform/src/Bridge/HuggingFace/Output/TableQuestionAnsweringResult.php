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
final readonly class TableQuestionAnsweringResult
{
    /**
     * @param array<int, string|int> $cells
     * @param array<string>          $aggregator
     */
    public function __construct(
        public string $answer,
        public array $cells = [],
        public array $aggregator = [],
    ) {
    }

    /**
     * @param array{answer: string, cells?: array<int, string|int>, aggregator?: array<string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['answer'],
            $data['cells'] ?? [],
            $data['aggregator'] ?? [],
        );
    }
}

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
final class SentenceSimilarityResult
{
    /**
     * @param array<float> $similarities
     */
    public function __construct(
        public readonly array $similarities,
    ) {
    }

    /**
     * @param array<float> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}

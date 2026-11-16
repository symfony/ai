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
final class FillMaskResult
{
    /**
     * @param MaskFill[] $fills
     */
    public function __construct(
        private readonly array $fills,
    ) {
    }

    /**
     * @return MaskFill[]
     */
    public function getFills(): array
    {
        return $this->fills;
    }
}

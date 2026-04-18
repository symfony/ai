<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * Represents a separate thinking block/part.
 */
final class ThinkingResult extends BaseResult
{
    public function __construct(
        private readonly ?string $content = null,
        private readonly ?string $signature = null,
    ) {
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }
}

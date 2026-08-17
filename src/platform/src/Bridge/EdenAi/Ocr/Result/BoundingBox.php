<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Ocr\Result;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class BoundingBox
{
    public function __construct(
        private readonly string $text,
        private readonly ?float $left = null,
        private readonly ?float $top = null,
        private readonly ?float $width = null,
        private readonly ?float $height = null,
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getLeft(): ?float
    {
        return $this->left;
    }

    public function getTop(): ?float
    {
        return $this->top;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }
}

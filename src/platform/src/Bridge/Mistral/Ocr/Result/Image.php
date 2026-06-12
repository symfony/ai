<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Ocr\Result;

/**
 * An image extracted from a page of a Mistral OCR result.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class Image
{
    public function __construct(
        private readonly string $id,
        private readonly ?int $topLeftX = null,
        private readonly ?int $topLeftY = null,
        private readonly ?int $bottomRightX = null,
        private readonly ?int $bottomRightY = null,
        private readonly ?string $imageBase64 = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTopLeftX(): ?int
    {
        return $this->topLeftX;
    }

    public function getTopLeftY(): ?int
    {
        return $this->topLeftY;
    }

    public function getBottomRightX(): ?int
    {
        return $this->bottomRightX;
    }

    public function getBottomRightY(): ?int
    {
        return $this->bottomRightY;
    }

    public function getImageBase64(): ?string
    {
        return $this->imageBase64;
    }
}
